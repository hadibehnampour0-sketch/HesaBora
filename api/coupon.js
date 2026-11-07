const { getLiveConfig } = require('./_config');

module.exports = async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Method Not Allowed' });
  }

  const body = req.body || {};
  const code = (body.code || '').trim().toUpperCase();
  const subtotal = parseInt(body.subtotal, 10) || 5000000;

  if (!code) {
    return res.status(400).json({
      success: false,
      message: 'لطفاً کد تخفیف را وارد کنید.'
    });
  }

  const config = await getLiveConfig();
  const coupons = Array.isArray(config.coupons) ? config.coupons : [];
  const matched = coupons.find(c => c.code.toUpperCase() === code && c.active);

  if (!matched) {
    return res.status(404).json({
      success: false,
      message: 'کد تخفیف واردشده نامعتبر است یا مهلت استفاده از آن به پایان رسیده است.'
    });
  }

  // Calculate discount
  let rawDiscount = Math.round((subtotal * matched.discountPercent) / 100);
  if (matched.maxDiscount && rawDiscount > matched.maxDiscount) {
    rawDiscount = matched.maxDiscount;
  }

  const finalTotal = Math.max(0, subtotal - rawDiscount);

  return res.status(200).json({
    success: true,
    code: matched.code,
    description: matched.description || `تخفیف ${matched.discountPercent}٪ نپتون`,
    discount_percent: matched.discountPercent,
    discount_amount: rawDiscount,
    discount_amount_formatted: new Intl.NumberFormat('fa-IR').format(rawDiscount) + ' تومان',
    original_total: subtotal,
    original_total_formatted: new Intl.NumberFormat('fa-IR').format(subtotal) + ' تومان',
    final_total: finalTotal,
    final_total_formatted: new Intl.NumberFormat('fa-IR').format(finalTotal) + ' تومان',
    message: `کد تخفیف ${matched.discountPercent}٪ با موفقیت اعمال شد.`
  });
};
