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
    shop: 'نسخه فروشگاهی و اصناف (پایه)',
    basic: 'نسخه فروشگاهی و اصناف (پایه)',
    commercial: 'نسخه تجاری و شرکتی (پرفروش‌ترین)',
    professional: 'نسخه تجاری و شرکتی (پرفروش‌ترین)',
    distribution: 'نسخه پخش مویرگی و ویزیتوری',
    manufacturing: 'نسخه تولیدی و صنعتی',
    enterprise: 'نسخه جامع سازمانی و هلدینگ'
  };

  const planPrices = {
    shop: 4800000,
    basic: 4800000,
    commercial: 11500000,
    professional: 11500000,
    distribution: 18900000,
    manufacturing: 21500000,
    enterprise: 34000000
  };

  const selectedPlan = body.plan || 'shop';
  const basePrice = planPrices[selectedPlan] || 4800000;
  const companiesCost = (parseInt(body.extra_companies, 10) || 0) * 1500000;
  const usersCost = (parseInt(body.extra_users, 10) || 0) * 1200000;
  const totalPrice = basePrice + companiesCost + usersCost;

  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const randSuffix = Math.random().toString(36).substring(2, 8).toUpperCase();
  const orderRef = `NEP-ORD-${dateStr}-${randSuffix}`;

  return res.status(200).json({
    success: true,
    order_reference: orderRef,
    plan_title: planTitles[selectedPlan] || selectedPlan,
    total_price_irr: totalPrice,
    total_price_formatted: new Intl.NumberFormat('fa-IR').format(totalPrice) + ' تومان',
    message: 'سفارش لایسنس شما با موفقیت ثبت شد. کارشناسان نپتون جهت فعال‌سازی نهایی و صدور فاکتور با شما تماس خواهند گرفت.'
  });
};
