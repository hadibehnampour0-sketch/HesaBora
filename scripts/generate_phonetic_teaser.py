import asyncio
import os
import wave
import edge_tts
import piper

# Master Diacritized & Validated Phonetic Script
MASTER_SCENES = [
    {
        'id': 'teaser_01',
        'text': 'اینترنت قطع شده... صفِ مشتری‌ها تا دمِ در کشیده... فردا سررسیدِ چکِ همکاره و مهلتِ دارایی هم داره تموم میشه!'
    },
    {
        'id': 'teaser_02',
        'text': 'کابوسِ سوختنِ هارد و پاک شدنِ حساب و کتابِ ده سال کاسبی... یا جریمه‌های سنگینِ مالیاتی... خواب رو از چشمت گرفته؟!'
    },
    {
        'id': 'teaser_03',
        'text': 'دیگه وقتِ اینه که روش‌های بیست سال پیش رو بریزی دور! با نِپتون، مدیریتِ مالی رو بیار به قرنِ جدید.'
    },
    {
        'id': 'teaser_04',
        'text': 'صد در صد آفلاین اول! حتی اگه کلِ اینترنتِ کشور قطع بشه، دخل و فروشگاهت یک ثانیه هم متوقف نمیشه.'
    },
    {
        'id': 'teaser_05',
        'text': 'ارسالِ مستقیم به سامانه‌ی مؤدیان؛ بدونِ حتی یک ریال کارمزد دادن به شرکت‌های مُعتَمَد!'
    },
    {
        'id': 'teaser_06',
        'text': 'دستیارِ هوشِ مصنوعی و امنیتِ سه، دو، یک... هر شب کلِ دیتابیسِ شرکتت، رمزنگاری‌شده و امن توی تلگرامِ خودت ذخیره میشه.'
    },
    {
        'id': 'teaser_07',
        'text': 'نِپتون، حسابدارِ همیشه‌بیدارِ کسب‌وکارِ تو. همین الان با تستِ رایگانِ چهارده روزه، از نِپتون دات‌آی‌آر دانلودش کن!'
    }
]

print("=== 1. Generating Piper (Amir & Ganji) ===")
voice_amir = piper.PiperVoice.load('models/piper/fa_IR-amir-medium.onnx', 'models/piper/fa_IR-amir-medium.onnx.json')
voice_ganji = piper.PiperVoice.load('models/piper/fa_IR-ganji-medium.onnx', 'models/piper/fa_IR-ganji-medium.onnx.json')
syn_config = piper.config.SynthesisConfig(length_scale=0.92)

for s in MASTER_SCENES:
    sid = s['id']
    txt = s['text']
    
    # Amir
    out_amir = f"public/audio/teaser/amir/{sid}.wav"
    with wave.open(out_amir, 'wb') as f:
        voice_amir.synthesize_wav(txt, f, syn_config=syn_config)
    print(f"Amir {sid}: {os.path.getsize(out_amir)} bytes")
    
    # Ganji
    out_ganji = f"public/audio/teaser/ganji/{sid}.wav"
    with wave.open(out_ganji, 'wb') as f:
        voice_ganji.synthesize_wav(txt, f, syn_config=syn_config)
    print(f"Ganji {sid}: {os.path.getsize(out_ganji)} bytes")

print("\n=== 2. Generating Microsoft Neural (Farid & Dilara) ===")
async def generate_neural():
    for s in MASTER_SCENES:
        sid = s['id']
        txt = s['text']
        
        c_farid = edge_tts.Communicate(txt, 'fa-IR-FaridNeural', rate='+5%', pitch='+0Hz')
        await c_farid.save(f"public/audio/teaser/farid/{sid}.mp3")
        
        c_dilara = edge_tts.Communicate(txt, 'fa-IR-DilaraNeural', rate='+3%', pitch='+0Hz')
        await c_dilara.save(f"public/audio/teaser/dilara/{sid}.mp3")
        print(f"Neural {sid} generated.")

asyncio.run(generate_neural())
print("\n=== ALL 4 REFINED SUITES GENERATED PERFECTLY! ===")
