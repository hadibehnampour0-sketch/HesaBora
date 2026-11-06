const crypto = require('crypto');

// Ed25519 root signing key (matches Neptune root public key b79f7b64c4e0f5a6f5e1ad74c2a6d1ee34a03d595472825cada05b46b4002e11)
const SEED_HEX = 'ea8fd18b6b0d8ffa8278dd934399f09379a5da4f763f2af53ee6f1cc73d2e3a1';
const PKCS8_DER = Buffer.concat([
  Buffer.from('302e020100300506032b657004220420', 'hex'),
  Buffer.from(SEED_HEX, 'hex')
]);
const ROOT_PRIV_KEY = crypto.createPrivateKey({ key: PKCS8_DER, format: 'der', type: 'pkcs8' });

function ksortRecursive(obj) {
  if (Array.isArray(obj)) {
    return obj.map(ksortRecursive);
  }
  if (obj !== null && typeof obj === 'object') {
    return Object.keys(obj)
      .sort()
      .reduce((acc, key) => {
        acc[key] = ksortRecursive(obj[key]);
        return acc;
      }, {});
  }
  return obj;
}

module.exports = async (req, res) => {
  // Allow CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-TOKEN');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Method Not Allowed' });
  }

  const body = req.body || {};
  const customerName = body.customer_name || 'کاربر آزمایشی نپتون';
  const phone = body.phone || '۰۹۱۲۰۰۰۰۰۰۰';
  const businessType = body.business_type || 'فروشگاهی';

  const now = Math.floor(Date.now() / 1000);
  const expiresAt = now + (14 * 86400);

  const payload = {
    v: 2,
    kid: 'neptune-root-2026',
    lic_id: crypto.randomUUID(),
    org: `${customerName} (${phone}) - ${businessType}`,
    tier: 'trial',
    modules: ['*'],
    limits: {
      companies: 2,
      users: 5,
      devices: 2
    },
    issued_at: now,
    not_before: now,
    expires_at: expiresAt,
    grace_days: 14,
    fingerprint: '*',
    issuer: 'Neptune Licensing Authority (Cloud Trial Gateway)',
    nonce: crypto.randomBytes(8).toString('hex')
  };

  const sortedPayload = ksortRecursive(payload);
  const canonicalJson = JSON.stringify(sortedPayload);
  const payloadBase64 = Buffer.from(canonicalJson, 'utf8').toString('base64url');

  // Sign using real root Ed25519 key
  const signature = crypto.sign(null, Buffer.from(canonicalJson, 'utf8'), ROOT_PRIV_KEY);
  const sigBase64 = signature.toString('base64url');

  const licenseKey = `NEPTUNE-LIC-v2.${payloadBase64}.${sigBase64}`;

  return res.status(200).json({
    success: true,
    message: 'لایسنس آزمایشی ۱۴ روزه نرم‌افزار نپتون با دسترسی کامل به تمام ماژول‌ها برای شما صادر شد.',
    license_key: licenseKey,
    edition: 'نسخه آزمایشی نامحدود نپتون (Trial)',
    days: 14,
    expires_at: new Date(expiresAt * 1000).toISOString().split('T')[0]
  });
};
