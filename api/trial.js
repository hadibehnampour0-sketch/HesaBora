const crypto = require('crypto');

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
  const customerName = body.customer_name || 'کاربر آزمایشی دخل';
  const phone = body.phone || '۰۹۱۲۰۰۰۰۰۰۰';
  const businessType = body.business_type || 'فروشگاهی';

  const now = Math.floor(Date.now() / 1000);
  const expiresAt = now + (14 * 86400);

  const payload = {
    v: 2,
    kid: 'dakhl-root-2026',
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
    issuer: 'Dakhl Licensing Authority (Cloud Trial Gateway)',
    nonce: crypto.randomBytes(8).toString('hex')
  };

  const canonicalJson = JSON.stringify(payload);
  const payloadBase64 = Buffer.from(canonicalJson, 'utf8').toString('base64url');

  // Sign using Ed25519
  const { privateKey } = crypto.generateKeyPairSync('ed25519');
  const signature = crypto.sign(null, Buffer.from(canonicalJson, 'utf8'), privateKey);
  const sigBase64 = signature.toString('base64url');

  const licenseKey = `DAKHL-LIC-v2.${payloadBase64}.${sigBase64}`;

  return res.status(200).json({
    success: true,
    message: 'لایسنس آزمایشی ۱۴ روزه با دسترسی کامل به تمام ماژول‌ها برای شما صادر شد.',
    license_key: licenseKey,
    edition: 'نسخه آزمایشی نامحدود (Trial)',
    days: 14,
    expires_at: new Date(expiresAt * 1000).toISOString().split('T')[0]
  });
};
