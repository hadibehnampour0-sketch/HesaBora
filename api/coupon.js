const fs = require('fs');
const path = require('path');

function getActiveCoupons() {
  try {
    const configPath = path.join(process.cwd(), 'public', 'site-config.json');
    if (fs.existsSync(configPath)) {
      const data = JSON.parse(fs.readFileSync(configPath, 'utf8'));
      if (Array.isArray(data.coupons)) {
        return data.coupons;
      }
    }
  } catch (e) {
    console.error('Error reading coupons:', e);
  }

  // Fallback defaults
  return [
    { code: "NEPTUNE20", discountPercent: 20, maxDiscount: 10000000, description: "۲۰٪ تخفیف ویژه رونمایی نسخه ۲.۵", active: true },
    { code: "START50", discountPercent: 50, maxDiscount: 2500000, description: "۵۰٪ تخفیف ویژه اصناف خرد (تا سقف ۲.۵ م.ت)", active: true },
    { code: "VIP15", discountPercent: 15, maxDiscount: 15000000, description: "۱۵٪ تخفیف ویژه خریداران شرکتی و سازمانی", active: true },
    { code: "OFFER10", discountPercent: 10, maxDiscount: 5000000, description: "۱۰٪ تخفیف عمومی برای تمامی پلن‌ها", active: true }
  ];
}

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

  const coupons = getActiveCoupons();
  const matched = coupons.find(c => c.code.toUpperCase() === code && c.active);

  if (!matched) {
    return res.status(404).json({
      success: false,
      message: 'کد تخفیف واردشده نامعتبر است یا منقضی شده است.'
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
    description: matched.description,
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
