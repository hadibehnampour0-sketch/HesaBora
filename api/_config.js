const fs = require('fs');
const path = require('path');

let cachedConfig = null;
let lastFetchTime = 0;
const CACHE_TTL_MS = 1500; // 1.5 seconds in-memory cache for ultra-fast responses

const BLOB_URL = 'https://exo0zviqm1abshhg.public.blob.vercel-storage.com/neptune-settings.json';

function getDefaultConfig() {
  try {
    const configPath = path.join(process.cwd(), 'public', 'site-config.json');
    if (fs.existsSync(configPath)) {
      return JSON.parse(fs.readFileSync(configPath, 'utf8'));
    }
  } catch (e) {
    console.error('Error reading local site-config.json:', e);
  }

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
      heroSubtitle: "راهکار جامع مدیریت مالی، حسابداری دوبل، انبارداری هوشمند، چک‌های صیادی و اتصال مستقیم به سامانه مودیان برای اصناف، شرکت‌ها و هلدینگ‌ها",
      ctaTrialText: "دریافت نسخه آزمایشی رایگان",
      ctaDownloadText: "دانلود مستقیم نسخه ویندوز",
      downloadUrl: "https://github.com/hadibehnampour0-sketch/HesaBora/releases/download/v2.5.0/Neptune-ERP-Setup-v2.5.exe",
      version: "v2.5.0",
      releaseDate: "آبان ۱۴۰۵",
      fileSize: "۲۱۷ مگابایت"
    },
    plans: {
      shop: { id: "shop", alias: "basic", name: "نسخه برنزی (اصناف و فروشگاهی)", price: 5000000, priceFormatted: "۵,۰۰۰,۰۰۰", period: "سالانه", badge: "پایه اصناف", badgeColor: "badge-ghost", description: "مناسب برای فروشگاه‌ها، مغازه‌ها، بوتیک‌ها و اصناف خرد نیازمند صندوق فروشگاهی سریع و سامانه مودیان.", maxCompanies: 1, maxUsers: 1 },
      basic: { id: "basic", alias: "shop", name: "نسخه برنزی (اصناف و فروشگاهی)", price: 5000000, priceFormatted: "۵,۰۰۰,۰۰۰", period: "سالانه", badge: "پایه اصناف", badgeColor: "badge-ghost", description: "مناسب برای فروشگاه‌ها، مغازه‌ها، بوتیک‌ها و اصناف خرد نیازمند صندوق فروشگاهی سریع و سامانه مودیان.", maxCompanies: 1, maxUsers: 1 },
      commercial: { id: "commercial", alias: "professional", name: "نسخه نقره‌ای (بازرگانی و شرکتی)", price: 15000000, priceFormatted: "۱۵,۰۰۰,۰۰۰", period: "سالانه", badge: "پرفروش‌ترین", badgeColor: "badge-primary", description: "ویژه دفاتر بازرگانی، عمده‌فروشان و شرکت‌های خدماتی نیازمند چند ارزی، مراکز هزینه، پیش‌فاکتور و چک‌های صیادی.", maxCompanies: 3, maxUsers: 3 },
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

async function getLiveConfig() {
  const now = Date.now();
  if (cachedConfig && (now - lastFetchTime) < CACHE_TTL_MS) {
    return cachedConfig;
  }

  // 1. Try to fetch from persistent Vercel Blob
  try {
    const res = await fetch(`${BLOB_URL}?t=${now}`, { cache: 'no-store' });
    if (res.ok) {
      const data = await res.json();
      if (data && data.site && data.plans) {
        // Ensure alias sync (shop/basic, commercial/professional)
        if (data.plans.basic && !data.plans.shop) data.plans.shop = data.plans.basic;
        if (data.plans.shop && !data.plans.basic) data.plans.basic = data.plans.shop;
        if (data.plans.professional && !data.plans.commercial) data.plans.commercial = data.plans.professional;
        if (data.plans.commercial && !data.plans.professional) data.plans.professional = data.plans.commercial;

        cachedConfig = data;
        lastFetchTime = now;
        return cachedConfig;
      }
    }
  } catch (err) {
    console.warn('Could not fetch config from Blob, using local fallback:', err.message);
  }

  // 2. Fallback to cached or default
  if (!cachedConfig) {
    cachedConfig = getDefaultConfig();
    lastFetchTime = now;
  }

  return cachedConfig;
}

async function saveLiveConfig(newConfig) {
  // Ensure dual alias consistency
  if (newConfig.plans) {
    if (newConfig.plans.basic && !newConfig.plans.shop) newConfig.plans.shop = newConfig.plans.basic;
    if (newConfig.plans.shop && !newConfig.plans.basic) newConfig.plans.basic = newConfig.plans.shop;
    if (newConfig.plans.professional && !newConfig.plans.commercial) newConfig.plans.commercial = newConfig.plans.professional;
    if (newConfig.plans.commercial && !newConfig.plans.professional) newConfig.plans.professional = newConfig.plans.commercial;
  }

  cachedConfig = newConfig;
  lastFetchTime = Date.now();

  // Try saving to Vercel Blob
  try {
    const { put } = require('@vercel/blob');
    const token = process.env.BLOB_READ_WRITE_TOKEN;
    if (token) {
      const blob = await put('neptune-settings.json', JSON.stringify(newConfig), {
        access: 'public',
        addRandomSuffix: false,
        allowOverwrite: true,
        token: token
      });
      return { success: true, url: blob.url };
    }
  } catch (err) {
    console.error('Failed to save to Vercel Blob:', err);
  }

  return { success: true, localOnly: true };
}

module.exports = {
  getLiveConfig,
  saveLiveConfig,
  getDefaultConfig
};
