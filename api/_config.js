const fs = require('fs');
const path = require('path');

let cachedConfig = null;
let lastFetchTime = 0;
const CACHE_TTL_MS = 1000; // 1 second in-memory cache

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

  const token = process.env.BLOB_READ_WRITE_TOKEN;
  if (token) {
    try {
      const { list } = require('@vercel/blob');
      const res = await list({ prefix: 'settings/config-', token });
      if (res && Array.isArray(res.blobs) && res.blobs.length > 0) {
        // Sort descending by uploadedAt to get the newest
        const sorted = res.blobs.sort((a, b) => new Date(b.uploadedAt) - new Date(a.uploadedAt));
        const latest = sorted[0];
        const fetchRes = await fetch(latest.url, { cache: 'no-store' });
        if (fetchRes.ok) {
          const data = await fetchRes.json();
          if (data && data.site && data.plans) {
            if (data.plans.basic && !data.plans.shop) data.plans.shop = data.plans.basic;
            if (data.plans.shop && !data.plans.basic) data.plans.basic = data.plans.shop;
            if (data.plans.professional && !data.plans.commercial) data.plans.commercial = data.plans.professional;
            if (data.plans.commercial && !data.plans.professional) data.plans.professional = data.plans.commercial;

            cachedConfig = data;
            lastFetchTime = now;
            return cachedConfig;
          }
        }
      }
    } catch (err) {
      console.warn('Could not list/fetch timestamped config from Blob:', err.message);
    }
  }

  if (!cachedConfig) {
    cachedConfig = getDefaultConfig();
    lastFetchTime = now;
  }

  return cachedConfig;
}

async function saveLiveConfig(newConfig) {
  // Ensure dual alias consistency and integer prices
  if (newConfig.plans) {
    for (let k in newConfig.plans) {
      if (newConfig.plans[k].price !== undefined) {
        const num = parseInt(newConfig.plans[k].price.toString().replace(/[^0-9]/g, ''), 10) || 0;
        newConfig.plans[k].price = num;
        newConfig.plans[k].priceFormatted = new Intl.NumberFormat('fa-IR').format(num);
      }
    }
    if (newConfig.plans.basic) newConfig.plans.shop = Object.assign({}, newConfig.plans.basic, { id: 'shop', alias: 'basic' });
    if (newConfig.plans.professional) newConfig.plans.commercial = Object.assign({}, newConfig.plans.professional, { id: 'commercial', alias: 'professional' });
  }

  cachedConfig = newConfig;
  lastFetchTime = Date.now();

  const token = process.env.BLOB_READ_WRITE_TOKEN;
  if (token) {
    try {
      const { put, list, del } = require('@vercel/blob');
      const time = Date.now();
      const filename = `settings/config-${time}.json`;
      const blob = await put(filename, JSON.stringify(newConfig), {
        access: 'public',
        addRandomSuffix: false,
        token
      });

      // Async cleanup of older configs (keep latest 2)
      list({ prefix: 'settings/config-', token }).then(res => {
        if (res && res.blobs && res.blobs.length > 2) {
          const sorted = res.blobs.sort((a, b) => new Date(b.uploadedAt) - new Date(a.uploadedAt));
          const toDelete = sorted.slice(2).map(b => b.url);
          if (toDelete.length > 0) del(toDelete, { token }).catch(() => {});
        }
      }).catch(() => {});

      return { success: true, url: blob.url };
    } catch (err) {
      console.error('Failed to save timestamped config to Vercel Blob:', err);
    }
  }

  return { success: true, localOnly: true };
}

module.exports = {
  getLiveConfig,
  saveLiveConfig,
  getDefaultConfig
};
