const { getLiveConfig } = require('./_config');

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

  const config = await getLiveConfig();
  const plans = config.plans || {};
  const coupons = Array.isArray(config.coupons) ? config.coupons : [];

  const selectedPlan = body.plan || 'shop';
  
  // Resolve plan price and title dynamically from live config
  let planObj = plans[selectedPlan];
  if (!planObj) {
    if (selectedPlan === 'shop' && plans.basic) planObj = plans.basic;
    if (selectedPlan === 'basic' && plans.shop) planObj = plans.shop;
    if (selectedPlan === 'commercial' && plans.professional) planObj = plans.professional;
    if (selectedPlan === 'professional' && plans.commercial) planObj = plans.commercial;
  }

  const basePrice = (planObj && planObj.price) ? parseInt(planObj.price, 10) : 5000000;
  const planTitle = (planObj && planObj.name) ? planObj.name : selectedPlan;

  const companiesCost = (parseInt(body.extra_companies, 10) || 0) * 1500000;
  const usersCost = (parseInt(body.extra_users, 10) || 0) * 1200000;
  const subtotal = basePrice + companiesCost + usersCost;

  // Coupon handling from live configuration
  let discountAmount = 0;
  let appliedCoupon = null;
  const couponCode = (body.coupon_code || '').trim().toUpperCase();

  if (couponCode) {
    const matched = coupons.find(c => c.code.toUpperCase() === couponCode && c.active);
    if (matched) {
      discountAmount = Math.round((subtotal * matched.discountPercent) / 100);
      if (matched.maxDiscount && discountAmount > matched.maxDiscount) {
        discountAmount = matched.maxDiscount;
      }
      appliedCoupon = {
        code: matched.code,
        description: matched.description || `تخفیف ${matched.discountPercent}٪ نپتون`,
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
    plan_title: planTitle,
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
