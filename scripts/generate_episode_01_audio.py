#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generate Full Persian Audio Suite for Episode 1 of Neptune ERP Masterclass:
Episode 1: «میز کار مدیریتی و دستیار هوش مالی نپتون (Executive Cockpit & AI Copilot)»
In accordance with:
- Decision D-138 (Voiceover & Acoustic Engineering Standard)
- Decision D-139 (B2B SaaS Video Storytelling Standard)
- Default Voice: Ostad Ahmad Ganji (fa_IR-ganji-medium)
- Alternate Voices: Amir (Rokh Podcast), Farid, Dilara
"""

import os
import io
import wave
import asyncio
import edge_tts
import piper

EPISODE_01_SCENES = [
    {
        'id': 'scene_01',
        'title': 'بحران تاریکی مالی و پرسش‌های بی‌پاسخ مدیر',
        # Hook (00:00 - 00:20)
        'clauses': [
            ('وقتی صبح واردِ دفترِ کارِت میشی،', 0.28),
            ('اولین سوالی که ذهنت رو درگیر میکنه چیه؟...', 0.38),
            ('امروز چقدر پول توی حسابِ بانکه؟!... دخلِ شرکت چقدر فروخته؟...', 0.35),
            ('و آیا سودِ واقعی بردی، یا فقط چرخشِ الکیِ پول بوده؟!', 0.35),
            ('روش‌های سنتی، شما رو ساعت‌ها منتظرِ گزارش‌های کاغذی میذارن؛ اما در بازارِ پرسرعتِ امروز، یک ساعت بی‌خبری، یعنی باختن!', 0.0)
        ],
        'full_text': 'وقتی صبح واردِ دفترِ کارِت میشی، اولین سوالی که ذهنت رو درگیر میکنه چیه؟... امروز چقدر پول توی حسابِ بانکه؟!... دخلِ شرکت چقدر فروخته؟!... و آیا سودِ واقعی بردی، یا فقط چرخشِ الکیِ پول بوده؟! روش‌های سنتی، شما رو ساعت‌ها منتظرِ گزارش‌های کاغذی میذارن؛ اما در بازارِ پرسرعتِ امروز، یک ساعت بی‌خبری، یعنی باختن!'
    },
    {
        'id': 'scene_02',
        'title': 'کابین خلبان نپتون و اشراف لحظه‌ای بر نقدینگی',
        # Guide & Cockpit (00:20 - 00:40)
        'clauses': [
            ('اینجاست که نِپتون، میزِ کارِ شما رو تبدیل به کابینِ خلبان میکنه!', 0.35),
            ('در یک نگاه، بدونِ نیاز به حسابدار و بدونِ یک دقیقه معطلی،', 0.28),
            ('موجودیِ کلِ بانک‌ها، دخلِ زنده، و سودِ خالصِ کسب‌وکارت،... ثانیه به ثانیه جلوی چشمته!', 0.0)
        ],
        'full_text': 'اینجاست که نِپتون، میزِ کارِ شما رو تبدیل به کابینِ خلبان میکنه!... در یک نگاه، بدونِ نیاز به حسابدار و بدونِ یک دقیقه معطلی، موجودیِ کلِ بانک‌ها، دخلِ زنده، و سودِ خالصِ کسب‌وکارت،... ثانیه به ثانیه جلوی چشمته!'
    },
    {
        'id': 'scene_03',
        'title': 'پایش تراز نقدینگی و رادار چک‌های صیادی',
        # Cash Flow & Cheque Radar (00:40 - 01:05)
        'clauses': [
            ('نمودارِ جریانِ وجوهِ نقد، ورود و خروجِ پول رو در هفت روزِ آینده پایش میکنه،', 0.28),
            ('تا شرکت هرگز با بحرانِ کمبودِ نقدینگی غافلگیر نشه!', 0.35),
            ('اما شاهکارِ امنیتِ مالی، رادارِ چک‌های صیادیه!...', 0.35),
            ('هیچ چکی فراموش نمیشه؛ سررسیدها هشدار داده میشن، و با یک کلیک وصول میشن، تا اعتبارِ بازاریِ شما، همیشه مثلِ کوه استوار بمونه!', 0.0)
        ],
        'full_text': 'نمودارِ جریانِ وجوهِ نقد، ورود و خروجِ پول رو در هفت روزِ آینده پایش میکنه، تا شرکت هرگز با بحرانِ کمبودِ نقدینگی غافلگیر نشه!... اما شاهکارِ امنیتِ مالی، رادارِ چک‌های صیادیه!... هیچ چکی فراموش نمیشه؛ سررسیدها هشدار داده میشن، و با یک کلیک وصول میشن، تا اعتبارِ بازاریِ شما، همیشه مثلِ کوه استوار بمونه!'
    },
    {
        'id': 'scene_04',
        'title': 'سرعت ارگونومیک کیبورد با کلیدهای F1 تا F8',
        # Shortcut Bar (01:05 - 01:25)
        'clauses': [
            ('در شلوغیِ بازار، سرعت همه‌چیزه!...', 0.35),
            ('حسابدارانِ حرفه‌ای وقتشون رو پای منوهای پیچیده و کلیک‌های اضافی تلف نمیکنن!', 0.30),
            ('با کلیدهای میانبرِ اف‌یک تا اف‌هشت، بدونِ لمسِ ماوس، در کسری از ثانیه فاکتور بزنید، چک ثبت کنید یا سند صادر کنید؛... سریع، ارگونومیک و بدونِ خطا!', 0.0)
        ],
        'full_text': 'در شلوغیِ بازار، سرعت همه‌چیزه!... حسابدارانِ حرفه‌ای وقتشون رو پای منوهای پیچیده و کلیک‌های اضافی تلف نمیکنن!... با کلیدهای میانبرِ اف‌یک تا اف‌هشت، بدونِ لمسِ ماوس، در کسری از ثانیه فاکتور بزنید، چک ثبت کنید یا سند صادر کنید؛... سریع، ارگونومیک و بدونِ خطا!'
    },
    {
        'id': 'scene_05',
        'title': 'دستیار هوش مالی جمنای و حسابرسی خودکار اسناد',
        # AI Copilot (01:25 - 01:50)
        'clauses': [
            ('اما انقلابِ واقعی، اینجاست: دستیارِ هوشِ مالیِ نِپتون!...', 0.40),
            ('نیازی به فرمول‌های پیچیده یا گزارش‌گیری‌های سنگین نیست!', 0.30),
            ('کافیه به فارسیِ ساده بپرسی: اسنادِ مشکوک رو پیدا کن، یا بگو کدوم کالا بیشترین سود رو ساخته؟...', 0.38),
            ('هوشِ مصنوعی مثلِ یک حسابرسِ ارشد، کلِ دیتابیس رو تحلیل میکنه، ناهنجاری‌ها رو قبل از ممیزیِ مالیات شکار میکنه، و حاشیه‌ی سودت رو تا بیست درصد ارتقا میده!', 0.0)
        ],
        'full_text': 'اما انقلابِ واقعی، اینجاست: دستیارِ هوشِ مالیِ نِپتون!... نیازی به فرمول‌های پیچیده یا گزارش‌گیری‌های سنگین نیست!... کافیه به فارسیِ ساده بپرسی: اسنادِ مشکوک رو پیدا کن، یا بگو کدوم کالا بیشترین سود رو ساخته؟... هوشِ مصنوعی مثلِ یک حسابرسِ ارشد، کلِ دیتابیس رو تحلیل میکنه، ناهنجاری‌ها رو قبل از ممیزیِ مالیات شکار میکنه، و حاشیه‌ی سودت رو تا بیست درصد ارتقا میده!'
    },
    {
        'id': 'scene_06',
        'title': 'تولد واقعیت نوین مدیریت مالی و دعوت به اقدام',
        # CTA & Resolution (01:50 - 02:10)
        'clauses': [
            ('دیگه دورانِ استرس، بی‌خبری از حساب و کتاب، و ترس از جریمه‌های مالیاتی تموم شد!...', 0.38),
            ('با نِپتون، یک حسابدارِ هوشمند و همیشه‌بیدار، بیست و چهار ساعته مراقبِ ثروت و اعتبارِ شماست!', 0.35),
            ('همین حالا با چهارده روز تستِ کاملاً رایگان و بدونِ نیاز به کارتِ بانکی، نِپتون را دانلود کنید: نِپتون دات‌آی‌آر!', 0.0)
        ],
        'full_text': 'دیگه دورانِ استرس، بی‌خبری از حساب و کتاب، و ترس از جریمه‌های مالیاتی تموم شد!... با نِپتون، یک حسابدارِ هوشمند و همیشه‌بیدار، بیست و چهار ساعته مراقبِ ثروت و اعتبارِ شماست!... همین حالا با چهارده روز تستِ کاملاً رایگان و بدونِ نیاز به کارتِ بانکی، نِپتون را دانلود کنید: نِپتون دات‌آی‌آر!'
    }
]

# Ensure output directories exist
for voice_name in ['ganji', 'amir', 'farid', 'dilara']:
    os.makedirs(f'public/audio/episodes/ep01/{voice_name}', exist_ok=True)

# 1. Generate Piper ONNX Audio (Ganji: Official Primary; Amir: Storyteller)
print("=== 1. Generating Episode 1 Piper Audio (Ganji & Amir) ===")
voice_ganji = piper.PiperVoice.load('models/piper/fa_IR-ganji-medium.onnx', 'models/piper/fa_IR-ganji-medium.onnx.json')
voice_amir = piper.PiperVoice.load('models/piper/fa_IR-amir-medium.onnx', 'models/piper/fa_IR-amir-medium.onnx.json')

syn_config_ganji = piper.config.SynthesisConfig(length_scale=0.94, noise_scale=0.72, noise_w_scale=0.85)
syn_config_amir = piper.config.SynthesisConfig(length_scale=0.92, noise_scale=0.78, noise_w_scale=0.88)

def synthesize_clauses_to_wav(voice, config, clauses, output_path):
    full_audio = bytearray()
    sr = 22050
    for text, pause_sec in clauses:
        buf = io.BytesIO()
        with wave.open(buf, 'wb') as w:
            voice.synthesize_wav(text, w, syn_config=config)
        buf.seek(0)
        with wave.open(buf, 'rb') as r:
            raw = r.readframes(r.getnframes())
            full_audio.extend(raw)
        if pause_sec > 0:
            silence_bytes = b'\x00' * int(sr * 2 * pause_sec)
            full_audio.extend(silence_bytes)
            
    with wave.open(output_path, 'wb') as f:
        f.setnchannels(1)
        f.setsampwidth(2)
        f.setframerate(sr)
        f.writeframes(full_audio)
    return len(full_audio)

for s in EPISODE_01_SCENES:
    sid = s['id']
    clauses = s['clauses']
    
    # Ganji (Primary)
    sz_g = synthesize_clauses_to_wav(voice_ganji, syn_config_ganji, clauses, f"public/audio/episodes/ep01/ganji/{sid}.wav")
    print(f"Ganji {sid}: {sz_g} bytes")
    
    # Amir (Alternate)
    sz_a = synthesize_clauses_to_wav(voice_amir, syn_config_amir, clauses, f"public/audio/episodes/ep01/amir/{sid}.wav")
    print(f"Amir {sid}: {sz_a} bytes")

# 2. Generate Microsoft Neural Audio (Farid & Dilara)
print("\n=== 2. Generating Episode 1 Microsoft Neural Audio (Farid & Dilara) ===")
async def generate_neural():
    for s in EPISODE_01_SCENES:
        sid = s['id']
        txt = s['full_text']
        
        # Farid: Punchy energetic promotional delivery
        c_farid = edge_tts.Communicate(txt, 'fa-IR-FaridNeural', rate='+5%', pitch='+1Hz')
        await c_farid.save(f"public/audio/episodes/ep01/farid/{sid}.mp3")
        
        # Dilara: Warm conversational corporate tone
        c_dilara = edge_tts.Communicate(txt, 'fa-IR-DilaraNeural', rate='+3%', pitch='+0Hz')
        await c_dilara.save(f"public/audio/episodes/ep01/dilara/{sid}.mp3")
        print(f"Neural {sid} generated.")

asyncio.run(generate_neural())
print("\n=== EPISODE 01 AUDIO GENERATION COMPLETE! ===")
