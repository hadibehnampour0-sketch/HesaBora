const fs = require('fs');
const path = require('path');

let inMemoryConfig = null;

function loadDefaultConfig() {
  if (inMemoryConfig) return inMemoryConfig;
  try {
    const configPath = path.join(process.cwd(), 'public', 'site-config.json');
    if (fs.existsSync(configPath)) {
      inMemoryConfig = JSON.parse(fs.readFileSync(configPath, 'utf8'));
      return inMemoryConfig;
    }
  } catch (e) {
    console.error('Failed to read site-config.json:', e);
  }

  // Fallback defaults
  return {
    site: {
      brandName: "نپتون",
      brandSubtitle: "نرم‌افزار جامع مالی و حسابداری",
      bannerActive: true,
      bannerText: "جشنواره عرضه رسمی نسخه ۲.۵ سامانه مالی نپتون با پشتیبانی رایگان سامانه مودیان",
      bannerBadge: "جشنواره ویژه",
      phone: "021-88884422",
      phoneDisplay: "۰۲۱-۸۸۸۸۴۴۲۲",
      mobile: "09120000000",
      telegram: "@NeptuneERP",
      telegramUrl: "https://t.me/NeptuneERP",
      supportHours: "شنبه تا پنج‌شنبه ۸:۰۰ الی ۱۸:۰۰",
      heroBadge: "نسخه ۲.۵ منتشر شد (پاییز ۱۴۰۵)",
      heroTitle: "سامانه یکپارچه مالی، ابری و آفلاین نپتون",
      heroSubtitle: "راهکار جامع مدیریت مالی، حسابداری دوبل، انبارداری هوشمند، چک‌های صیادی و اتصال مستقیم به سامانه مودیان",
      ctaTrialText: "دریافت نسخه آزمایشی رایگان",
      ctaDownloadText: "دانلود مستقیم نسخه ویندوز",
      downloadUrl: "https://neptune-erp.vercel.app/download/windows",
      version: "v2.5.0",
      releaseDate: "آبان ۱۴۰۵",
      fileSize: "۲۱۷ مگابایت"
    },
    plans: {
      basic: { id: "basic", alias: "shop", name: "نسخه برنزی (اصناف و فروشگاهی)", price: 5000000, priceFormatted: "۵,۰۰۰,۰۰۰", period: "سالانه", badge: "پایه اصناف", badgeColor: "badge-ghost", description: "مناسب برای فروشگاه‌ها، مغازه‌ها، بوتیک‌ها و اصناف خرد نیازمند صندوق فروشگاهی سریع و سامانه مودیان.", maxCompanies: 1, maxUsers: 1 },
      professional: { id: "professional", alias: "commercial", name: "نسخه نقره‌ای (بازرگانی و شرکتی)", price: 15000000, priceFormatted: "۱۵,۰۰۰,۰۰۰", period: "سالانه", badge: "پرفروش‌ترین", badgeColor: "badge-primary", description: "ویژه دفاتر بازرگانی، عمده‌فروشان و شرکت‌های خدماتی نیازمند چند ارزی، مراکز هزینه، پیش‌فاکتور و چک‌های صیادی.", maxCompanies: 3, maxUsers: 3 },
      distribution: { id: "distribution", name: "نسخه طلایی (پخش مویرگی و ویزیتوری)", price: 35000000, priceFormatted: "۳۵,۰۰۰,۰۰۰", period: "سالانه", badge: "تخصصی توزیع", badgeColor: "badge-secondary", description: "ویژه شرکت‌های پخش گرم و سرد، ویزیتوری میدانی، انبار ماشین (وان)، مسیربندی فروش و تسویه روزانه.", maxCompanies: 5, maxUsers: 5 },
      manufacturing: { id: "manufacturing", name: "نسخه پلاتینیوم (تولیدی و صنعتی)", price: 50000000, priceFormatted: "۵۰,۰۰۰,۰۰۰", period: "سالانه", badge: "تخصصی تولید", badgeColor: "badge-warning", description: "ویژه کارخانجات و کارگاه‌های تولیدی، فرمول ساخت (BOM)، بهای تمام‌شده کالای ساخته‌شده، خطوط تولید و سربار.", maxCompanies: 5, maxUsers: 5 },
      enterprise: { id: "enterprise", name: "نسخه الماس (سازمانی و هلدینگ)", price: 80000000, priceFormatted: "۸۰,۰۰۰,۰۰۰", period: "سالانه یا دائمی", badge: "جامع‌ترین", badgeColor: "badge-accent", description: "برای کارخانجات، شرکت‌های پخش مویرگی و هلدینگ‌هایی که به تمامی امکانات بدون هیچ‌گونه محدودیتی نیاز دارند.", maxCompanies: "نامحدود", maxUsers: "نامحدود" }
    },
    coupons: [
      { code: "NEPTUNE20", discountPercent: 20, maxDiscount: 10000000, description: "۲۰٪ تخفیف ویژه رونمایی نسخه ۲.۵", active: true },
      { code: "START50", discountPercent: 50, maxDiscount: 2500000, description: "۵۰٪ تخفیف ویژه اصناف خرد (تا سقف ۲.۵ م.ت)", active: true },
      { code: "VIP15", discountPercent: 15, maxDiscount: 15000000, description: "۱۵٪ تخفیف ویژه خریداران شرکتی و سازمانی", active: true },
      { code: "OFFER10", discountPercent: 10, maxDiscount: 5000000, description: "۱۰٪ تخفیف عمومی برای تمامی پلن‌ها", active: true }
    ]
  };
}

module.exports = async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  const currentConfig = loadDefaultConfig();

  if (req.method === 'POST') {
    const body = req.body || {};
    if (body.site) {
      currentConfig.site = Object.assign(currentConfig.site || {}, body.site);
    }
    if (body.plans) {
      currentConfig.plans = Object.assign(currentConfig.plans || {}, body.plans);
    }
    if (Array.isArray(body.coupons)) {
      currentConfig.coupons = body.coupons;
    }
    inMemoryConfig = currentConfig;
    return res.status(200).json({
      success: true,
      message: 'تنظیمات سایت با موفقیت به‌روزرسانی شد.',
      config: currentConfig
    });
  }

  return res.status(200).json({
    success: true,
    config: currentConfig
  });
};
