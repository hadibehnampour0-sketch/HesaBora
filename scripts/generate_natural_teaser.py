import asyncio
import os
import shutil
import edge_tts

scenes = [
    {
        'id': 'teaser_01',
        'text': 'اینترنت قطع شده... صفِ مشتریا تا دمِ در کشیده... فردا سررسیدِ چکِ همکاره و مهلتِ دارایی هم داره تموم میشه!'
    },
    {
        'id': 'teaser_02',
        'text': 'کابوسِ سوختنِ هارد و پریدنِ حساب‌کتابِ ده سال کاسبی... یا جریمه‌های سنگینِ مالیاتی... خواب رو از چشمت گرفته؟!'
    },
    {
        'id': 'teaser_03',
        'text': 'دیگه وقتشه روش‌های بیست سال پیش رو بریزی دور! با نپتون، مدیریتِ مالی رو بیار به قرنِ جدید.'
    },
    {
        'id': 'teaser_04',
        'text': 'صد در صد آفلاین اول! حتی اگه کل اینترنتِ کشور قطع بشه، دخل و فروشگاهت یه ثانیه هم متوقف نمیشه.'
    },
    {
        'id': 'teaser_05',
        'text': 'ارسال مستقیم به سامانه مودیان؛ بدونِ حتی یه قرون باج دادن به شرکت‌های معتمد!'
    },
    {
        'id': 'teaser_06',
        'text': 'دستیارِ هوش مصنوعی و امنیتِ سه، دو، یک... هر شب کل دیتابیسِ شرکتت، رمزنگاری‌شده و امن توی تلگرامِ خودت ذخیره میشه.'
    },
    {
        'id': 'teaser_07',
        'text': 'نپتون، حسابدارِ همیشه‌بیدارِ کسب‌وکارِ تو. همین الان با تستِ رایگانِ چهارده روزه، از نپتون دات‌آی‌آر دانلودش کن!'
    }
]

os.makedirs('public/audio/teaser/farid', exist_ok=True)
os.makedirs('public/audio/teaser/dilara', exist_ok=True)
os.makedirs('public/audio/teaser', exist_ok=True)

async def generate():
    for s in scenes:
        sid = s['id']
        txt = s['text']
        print(f"Generating {sid}...")
        
        # Farid (Dynamic, punchy Persian commercial voice)
        c_farid = edge_tts.Communicate(txt, 'fa-IR-FaridNeural', rate='+6%', pitch='+0Hz')
        await c_farid.save(f"public/audio/teaser/farid/{sid}.mp3")
        
        # Dilara (Warm, fluent, natural conversational tone)
        c_dilara = edge_tts.Communicate(txt, 'fa-IR-DilaraNeural', rate='+4%', pitch='+0Hz')
        await c_dilara.save(f"public/audio/teaser/dilara/{sid}.mp3")
        
        # Default active voice is Farid (can also be switched to Dilara)
        shutil.copyfile(f"public/audio/teaser/farid/{sid}.mp3", f"public/audio/teaser/{sid}.mp3")

    print("SUCCESS: All teaser audio generated for Farid & Dilara!")

if __name__ == '__main__':
    asyncio.run(generate())
