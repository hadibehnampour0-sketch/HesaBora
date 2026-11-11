# -*- coding: utf-8 -*-
import os
import wave
import piper
import piper.config
import asyncio
import edge_tts

base_dir = "public/audio/episodes/ep04"
voices = ["ganji", "amir", "dilara", "farid"]
for v in voices:
    os.makedirs(f"{base_dir}/{v}", exist_ok=True)

scenes = [
    (
        "scene_01",
        "جریمه ماده ۲۲: ۲۸۰ میلیون تومان، فقط به خاطر چند روز تاخیر یا اشتباه در ارسال فاکتور! تا حالا برگه تشخیص جریمه مالیاتی روی میزتان فرود آمده؟! قانونی که با هیچ کسب‌وکاری شوخی ندارد و در یک چشم به هم زدن، ۱۰ درصد از کل فروش سالانه‌تان را دود می‌کند!"
    ),
    (
        "scene_02",
        "اما کابوس اصلی کجاست؟! شرکت‌های معتمد که سالانه ده‌ها میلیون تومان حق اشتراک می‌گیرند، نرم‌افزارهای واسطه‌ای که با هر تغییر قانون قطع می‌شوند، و ارورهای گیج‌کننده‌ای که هیچ حسابداری سر از آن‌ها درنمی‌آورد! تا کی باید برای ارسال یک فاکتور ساده باج بدهید؟!"
    ),
    (
        "scene_03",
        "راهکارِ نپتون: ارسالِ مستقیم، بی‌واسطه و ۱۰۰٪ رایگان به کارپوشه سامانه مودیان! فقط با یک بار ثبت کلید اختصاصی، نپتون در همان لحظه صدور فاکتور، صورتحساب را طبق آخرین الگوی سازمان مالیاتی رمزگذاری کرده و با یک کلیک مستقیم به کارپوشه می‌فرستد!"
    ),
    (
        "scene_04",
        "دیگر نگرانِ خطاهای شناسه کالا و عدم تطابق نرخ ارزش افزوده نباشید! نپتون تمام فیلدهای قانونی را خودکار اعتبارسنجی می‌کند، کد مالیاتی ۲۲ رقمی را آنی دریافت می‌کند و وضعیت صورتحساب را زنده در کارپوشه استعلام می‌گیرد؛ بدون حتی ۱ ثانیه معطلی!"
    ),
    (
        "scene_05",
        "قبل از نپتون: ترس از رد شدن فاکتورها، هزینه‌های سنگین واسطه‌ها و دلهره ممیزی مالیاتی! بعد از نپتون: شفافیت مالیاتی مطلق، صفر شدن ریسک جرایم سنگین ماده ۲۲ و ارسال صورتحساب‌ها تنها با زدن دکمه تایید فاکتور فروش!"
    ),
    (
        "scene_06",
        "اجازه ندهید حتی یک ریال از درآمدتان خرج جرایم مالیاتی یا اشتراک‌های بیهوده شود! همین حالا با چهارده روز تست کاملاً رایگان، سپر مالیاتی نپتون را برای کسب‌وکارتان فعال کنید یا زیر همین ویدیو، کلمه مودیان را کامنت بگذارید: نپتون دات‌آی‌آر!"
    )
]

# 1. Generate Ganji (Primary Piper Voice)
print("--- Generating Ganji Piper audios for Episode 4 ---")
ganji_onnx = "models/piper/fa_IR-ganji-medium.onnx"
ganji_json = "models/piper/fa_IR-ganji-medium.onnx.json"
voice_ganji = piper.PiperVoice.load(ganji_onnx, ganji_json)
syn_config = piper.config.SynthesisConfig(length_scale=0.92, noise_scale=0.74, noise_w_scale=0.86)

for sid, txt in scenes:
    out_wav = f"{base_dir}/ganji/{sid}.wav"
    with wave.open(out_wav, "wb") as wav_file:
        voice_ganji.synthesize_wav(txt, wav_file, syn_config=syn_config)
    print(f"Ganji generated: {out_wav}")

# 2. Generate Amir (Conversational Piper Voice)
print("--- Generating Amir Piper audios for Episode 4 ---")
amir_onnx = "models/piper/fa_IR-amir-medium.onnx"
amir_json = "models/piper/fa_IR-amir-medium.onnx.json"
voice_amir = piper.PiperVoice.load(amir_onnx, amir_json)

for sid, txt in scenes:
    out_wav = f"{base_dir}/amir/{sid}.wav"
    with wave.open(out_wav, "wb") as wav_file:
        voice_amir.synthesize_wav(txt, wav_file, syn_config=syn_config)
    print(f"Amir generated: {out_wav}")

# 3. Generate Edge-TTS (Dilara & Farid)
async def generate_edge():
    for voice_name, voice_id in [("dilara", "fa-IR-DilaraNeural"), ("farid", "fa-IR-FaridNeural")]:
        print(f"--- Generating {voice_name} Edge-TTS audios for Episode 4 ---")
        for sid, txt in scenes:
            out_mp3 = f"{base_dir}/{voice_name}/{sid}.mp3"
            for attempt in range(4):
                try:
                    comm = edge_tts.Communicate(txt, voice=voice_id, rate="+0%", pitch="+0Hz")
                    await comm.save(out_mp3)
                    if os.path.getsize(out_mp3) > 1000:
                        print(f"{voice_name} generated: {out_mp3}")
                        break
                except Exception as e:
                    print(f"Attempt {attempt+1} failed for {voice_name}: {e}")
                    await asyncio.sleep(1.5)

asyncio.run(generate_edge())
print("All Episode 4 audio generation completed successfully!")
