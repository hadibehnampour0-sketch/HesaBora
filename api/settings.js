const { getLiveConfig, saveLiveConfig } = require('./_config');

module.exports = async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  const currentConfig = await getLiveConfig();

  if (req.method === 'POST') {
    const body = req.body || {};
    const updated = JSON.parse(JSON.stringify(currentConfig));

    if (body.site && typeof body.site === 'object') {
      updated.site = Object.assign(updated.site || {}, body.site);
    }
    if (body.plans && typeof body.plans === 'object') {
      updated.plans = Object.assign(updated.plans || {}, body.plans);
    }
    if (Array.isArray(body.coupons)) {
      updated.coupons = body.coupons;
    }

    const saveResult = await saveLiveConfig(updated);

    return res.status(200).json({
      success: true,
      message: 'تنظیمات وب‌سایت با موفقیت در فضای ابری نپتون ذخیره و در سراسر سایت اعمال شد.',
      config: updated,
      cloudSync: saveResult
    });
  }

  return res.status(200).json({
    success: true,
    config: currentConfig
  });
};
