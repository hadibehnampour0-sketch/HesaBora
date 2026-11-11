# -*- coding: utf-8 -*-
import os
import wave
import piper
import piper.config
import asyncio
import edge_tts

# Destination directories
base_dir = "public/audio/episodes/ep02"
voices = ["ganji", "amir", "dilara", "farid"]
for v in voices:
    os.makedirs(f"{base_dir}/{v}", exist_ok=True)

scenes = [
    (
        "scene_01",
        "ساعتِ هشتِ شب... اوجِ شلوغیِ فروشگاه... ده‌ها مشتری در صف... و ناگهان: اینترنت قطع می‌شود! مشتری‌ها کلافه، کارت‌خوان‌ها معطل، و صندوقِ سنتی که ارور می‌دهد: عدمِ اتصال به سرور! آیا تا به حال این کابوس را در کسب‌وکارتان زندگی کرده‌اید؟!"
    ),
    (
        "scene_02",
        "خیلی از نرم‌افزارهای ابری ادعای مدرن بودن دارند؛ اما با یک قطعیِ ساده‌ی شبکه، فروشگاه به گِل می‌نشیند! مشتری سبدِ خرید را روی میز می‌گذارد و می‌رود! در بازارِ پرسرعتِ امروز، یک ساعت توقفِ صندوق، یعنی دود شدنِ میلیون‌ها تومان سودِ خالص!"
    ),
    (
        "scene_03",
        "اما راهکارِ نپتون چیست؟! پایانه فروشگاهیِ صد در صد آفلاین اول! نپتون برای صدورِ فاکتور و ثبتِ دخل، به هیچ اینترنتی نیاز ندارد! دیتابیسِ امن، روی سیستمِ خودتان مستقر است؛ سرعتِ صدورِ فاکتور: کمتر از یک ثانیه! و هر زمان که اینترنت وصل شد، تمامِ داده‌ها خودکار و بدونِ تداخل با شعبه‌ی مرکزی همگام می‌شوند!"
    ),
    (
        "scene_04",
        "بارکدِ کالا را اسکن کنید؛ قیمت، تخفیف و موجودیِ انبار در یک‌صدمِ ثانیه محاسبه و فیشِ حرارتی چاپ می‌شود! مبلغِ فاکتور مستقیماً روی دستگاهِ پوزِ بانکی می‌نشیند؛ بدونِ نیاز به تایپِ دستیِ ارقام و بدونِ حتی یک ریال مغایرت! چند صندوقِ همزمان، بدونِ فاکتورِ تکراری!"
    ),
    (
        "scene_05",
        "قبل از نپتون: استرسِ قطعیِ اینترنت، غرولندِ مشتریان در صف و اشتباهاتِ حسابدار. بعد از نپتون: سرعتِ موشکی در صندوق، انبارداریِ بی‌خطا، و آرامشِ کامل در اوجِ شلوغیِ شبِ عید!"
    ),
    (
        "scene_06",
        "همین حالا، بدونِ نیاز به کارتِ بانکی، صندوقِ فروشگاهیِ نپتون را با چهارده روز تستِ رایگان دانلود کنید یا زیرِ همین پست، کلمه صندوق را کامنت بگذارید: نپتون دات‌آی‌آر!"
    )
]

# 1. Generate Ganji (Primary Piper Voice - Decision D-138)
print("Generating Ganji Piper audios...")
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
print("Generating Amir Piper audios...")
amir_onnx = "models/piper/fa_IR-amir-medium.onnx"
amir_json = "models/piper/fa_IR-amir-medium.onnx.json"
voice_amir = piper.PiperVoice.load(amir_onnx, amir_json)

for sid, txt in scenes:
    out_wav = f"{base_dir}/amir/{sid}.wav"
    with wave.open(out_wav, "wb") as wav_file:
        voice_amir.synthesize_wav(txt, wav_file, syn_config=syn_config)
    print(f"Amir generated: {out_wav}")

# 3. Generate Edge TTS (Dilara & Farid)
async def generate_edge():
    print("Generating Edge TTS audios...")
    for sid, txt in scenes:
        # Dilara
        d_out = f"{base_dir}/dilara/{sid}.mp3"
        comm_d = edge_tts.Communicate(txt, voice="fa-IR-DilaraNeural", rate="+0%", pitch="+0Hz")
        await comm_d.save(d_out)
        print(f"Dilara generated: {d_out}")

        # Farid
        f_out = f"{base_dir}/farid/{sid}.mp3"
        comm_f = edge_tts.Communicate(txt, voice="fa-IR-FaridNeural", rate="+0%", pitch="+0Hz")
        await comm_f.save(f_out)
        print(f"Farid generated: {f_out}")

asyncio.run(generate_edge())
print("All Episode 2 audio tracks generated successfully!")
