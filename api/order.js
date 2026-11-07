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
  } catch (e) {}

  return [
    { code: "NEPTUNE20", discountPercent: 20, maxDiscount: 10000000, active: true },
    { code: "START50", discountPercent: 50, maxDiscount: 2500000, active: true },
    { code: "VIP15", discountPercent: 15, maxDiscount: 15000000, active: true },
    { code: "OFFER10", discountPercent: 10, maxDiscount: 5000000, active: true }
  ];
}

module.exports = async (req, res) => {
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
  const customerName = body.customer_name || 'خریدار گرامی';
  const phone = body.phone || '۰۹۱۲۰۰۰۰۰۰۰';

  const planTitles = {
    shop: 'نسخه برنزی (اصناف و فروشگاهی)',
    basic: 'نسخه برنزی (اصناف و فروشگاهی)',
    commercial: 'نسخه نقره‌ای (بازرگانی و شرکتی)',
    professional: 'نسخه نقره‌ای (بازرگانی و شرکتی)',
    distribution: 'نسخه طلایی (پخش مویرگی و ویزیتوری)',
    manufacturing: 'نسخه پلاتینیوم (تولیدی و صنعتی)',
    enterprise: 'نسخه الماس (سازمانی و هلدینگ)'
  };

  const planPrices = {
    shop: 5000000,
    basic: 5000000,
    commercial: 15000000,
    professional: 15000000,
    distribution: 35000000,
    manufacturing: 50000000,
    enterprise: 80000000
  };

  const selectedPlan = body.plan || 'shop';
  const basePrice = planPrices[selectedPlan] || 5000000;
  const companiesCost = (parseInt(body.extra_companies, 10) || 0) * 1500000;
  const usersCost = (parseInt(body.extra_users, 10) || 0) * 1200000;
  const subtotal = basePrice + companiesCost + usersCost;

  // Coupon handling
  let discountAmount = 0;
  let appliedCoupon = null;
  const couponCode = (body.coupon_code || '').trim().toUpperCase();

  if (couponCode) {
    const coupons = getActiveCoupons();
    const matched = coupons.find(c => c.code.toUpperCase() === couponCode && c.active);
    if (matched) {
      discountAmount = Math.round((subtotal * matched.discountPercent) / 100);
      if (matched.maxDiscount && discountAmount > matched.maxDiscount) {
        discountAmount = matched.maxDiscount;
      }
      appliedCoupon = {
        code: matched.code,
        discount_percent: matched.discountPercent,
        discount_amount: discountAmount
      };
    }
  }

  const finalPrice = Math.max(0, subtotal - discountAmount);

  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const randSuffix = Math.random().toString(36).substring(2, 8).toUpperCase();
  const orderRef = `NEP-ORD-${dateStr}-${randSuffix}`;

  return res.status(200).json({
    success: true,
    order_reference: orderRef,
    plan_title: planTitles[selectedPlan] || selectedPlan,
    subtotal_irr: subtotal,
    subtotal_formatted: new Intl.NumberFormat('fa-IR').format(subtotal) + ' تومان',
    discount_amount: discountAmount,
    discount_amount_formatted: discountAmount > 0 ? (new Intl.NumberFormat('fa-IR').format(discountAmount) + ' تومان') : null,
    coupon_applied: appliedCoupon,
    total_price_irr: finalPrice,
    total_price_formatted: new Intl.NumberFormat('fa-IR').format(finalPrice) + ' تومان',
    message: discountAmount > 0
      ? `سفارش شما با اعمال کد تخفیف (${new Intl.NumberFormat('fa-IR').format(discountAmount)} تومان تخفیف) با موفقیت ثبت شد.`
      : 'سفارش لایسنس شما با موفقیت ثبت شد. کارشناسان نپتون جهت فعال‌سازی نهایی و صدور فاکتور با شما تماس خواهند گرفت.'
  });
};
