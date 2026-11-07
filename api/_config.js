const fs = require('fs');
const path = require('path');

let cachedConfig = null;
let lastFetchTime = 0;
const CACHE_TTL_MS = 1000; // 1 second in-memory cache

function parseIranianNumber(val) {
  if (val === undefined || val === null) return 0;
  const str = val.toString()
    .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
    .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
    .replace(/[^0-9]/g, '');
  return parseInt(str, 10) || 0;
}

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
    ],
    addons: {
      pos_retail: { id: "pos_retail", title: "صندوق فروشگاهی سریع (POS)", icon: "🛒", price: 1500000, priceFormatted: "۱,۵۰۰,۰۰۰", category: "فروش و صندوق", desc: "رابط لمسی صندوق، اتصال مستقیم به بارکدخوان، چاپگر فیش، کشوی پول و انواع کارتخوان PC-POS بانکی." },
      distribution: { id: "distribution", title: "پخش مویرگی و ویزیتوری", icon: "🚚", price: 4500000, priceFormatted: "۴,۵۰۰,۰۰۰", category: "پخش و توزیع", desc: "مدیریت انبار خودرو (وان)، مسیربندی ویزیتورها، سفارش‌گیری تبلت (سرد و گرم)، کاتالوگ دیجیتال و تسویه فاکتور." },
      production_bom: { id: "production_bom", title: "فرمول ساخت و تولید (BOM)", icon: "⚙️", price: 5500000, priceFormatted: "۵,۵۰۰,۰۰۰", category: "تولید و صنعتی", desc: "تعریف ساختار چندمرحله‌ای محصول (BOM)، صدور دستور ساخت، محاسبه مکانیزه بهای تمام‌شده و تسهیم سربار مستقیم." },
      hr_payroll: { id: "hr_payroll", title: "حقوق، دستمزد و کارگزینی", icon: "👥", price: 2500000, priceFormatted: "۲,۵۰۰,۰۰۰", category: "منابع انسانی", desc: "احکام کارگزینی، ثبت تردد و اضافه‌کاری، فیش حقوقی اداره کار، دیسکت بیمه تامین اجتماعی و مالیات حقوق." },
      sms_automation: { id: "sms_automation", title: "اتوماسیون پیامک هوشمند", icon: "💬", price: 1200000, priceFormatted: "۱,۲۰۰,۰۰۰", category: "ارتباط با مشتری", desc: "ارسال خودکار پیامک صدور فاکتور، یادآوری سررسید چک، مانده‌حساب بدهکاران و تبریک مناسبت‌ها با خط خدماتی." },
      customer_loyalty: { id: "customer_loyalty", title: "باشگاه مشتریان و کیف پول", icon: "🎁", price: 1400000, priceFormatted: "۱,۴۰۰,۰۰۰", category: "وفاداری و مارکتینگ", desc: "محاسبه خودکار امتیاز و کش‌بک خرید، تعریف سطوح وفاداری مشتریان و کیف پول شارژی جهت خریدهای بعدی." },
      telephony_voip: { id: "telephony_voip", title: "کالرآیدی و تلفن سانترال (VoIP)", icon: "📞", price: 1100000, priceFormatted: "۱,۱۰۰,۰۰۰", category: "ارتباطات تلفنی", desc: "نمایش پاپ‌آپ پرونده مشتری، مانده‌حساب، آخرین سفارش‌ها و وضعیت چک‌ها همزمان با زنگ خوردن تلفن." },
      official_inquiries: { id: "official_inquiries", title: "استعلام شاهکار و اصالت شبا", icon: "🔍", price: 800000, priceFormatted: "۸۰۰,۰۰۰", category: "اعتبارسنجی و مالیات", desc: "احراز هویت پیامکی شاهکار (تطابق کدملی و سیم‌کارت)، صحت‌سنجی شماره شبا، کارت بانکی و نام صاحب‌حساب." },
      installment_financing: { id: "installment_financing", title: "فروش اقساطی و محاسبات سود", icon: "💳", price: 1500000, priceFormatted: "۱,۵۰۰,۰۰۰", category: "مالی و اقساط", desc: "تقسیط فاکتور با فرمول بانکی یا توافقی، محاسبه جریمه دیرکرد روزشمار، چاپ دفترچه اقساط و پیامک یادآوری قسط." },
      ai_copilot: { id: "ai_copilot", title: "دستیار مالی هوش مصنوعی و OCR", icon: "✨", price: 3200000, priceFormatted: "۳,۲۰۰,۰۰۰", category: "هوش مصنوعی", desc: "تحلیل مالی هوشمند، تبدیل عکس فاکتور کاغذی به سند مکانیزه حسابداری با OCR فارسی و فرمان صوتی." },
      migration_tools: { id: "migration_tools", title: "مهاجرت هوشمند از هلو و سپیدار", icon: "🔄", price: 800000, priceFormatted: "۸۰۰,۰۰۰", category: "ابزارهای داده", desc: "انتقال خودکار ۱۰۰٪ کاردکس، اشخاص، سرفصل‌ها، انبارها و مانده‌حساب‌ها از هلو، سپیدار، پارسیان و همکاران سیستم." }
    }
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
            if (!data.addons) data.addons = getDefaultConfig().addons;

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
      const p = newConfig.plans[k];
      let num = parseIranianNumber(p.price);
      if (!num && p.priceFormatted) {
        num = parseIranianNumber(p.priceFormatted);
      }
      p.price = num;
      p.priceFormatted = new Intl.NumberFormat('fa-IR').format(num);
    }
    if (newConfig.plans.basic) {
      newConfig.plans.shop = Object.assign({}, newConfig.plans.basic, { id: 'shop', alias: 'basic' });
    }
    if (newConfig.plans.shop && !newConfig.plans.basic) {
      newConfig.plans.basic = Object.assign({}, newConfig.plans.shop, { id: 'basic', alias: 'shop' });
    }
    if (newConfig.plans.professional) {
      newConfig.plans.commercial = Object.assign({}, newConfig.plans.professional, { id: 'commercial', alias: 'professional' });
    }
    if (newConfig.plans.commercial && !newConfig.plans.professional) {
      newConfig.plans.professional = Object.assign({}, newConfig.plans.commercial, { id: 'professional', alias: 'commercial' });
    }
  }

  if (newConfig.addons) {
    for (let k in newConfig.addons) {
      const a = newConfig.addons[k];
      let num = parseIranianNumber(a.price);
      if (!num && a.priceFormatted) {
        num = parseIranianNumber(a.priceFormatted);
      }
      a.price = num;
      a.priceFormatted = new Intl.NumberFormat('fa-IR').format(num);
    }
  }

  // Try to update local file if writable
  try {
    const configPath = path.join(process.cwd(), 'public', 'site-config.json');
    if (fs.existsSync(configPath)) {
      fs.writeFileSync(configPath, JSON.stringify(newConfig, null, 2), 'utf8');
    }
  } catch (e) {}

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
