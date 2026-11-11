# -*- coding: utf-8 -*-
import os
import wave
import piper
import piper.config
import asyncio
import edge_tts

# Destination directories
base_dir = "public/audio/episodes/ep03"
voices = ["ganji", "amir", "dilara", "farid"]
for v in voices:
    os.makedirs(f"{base_dir}/{v}", exist_ok=True)

scenes = [
    (
        "scene_01",
        "روزی چقدر از سرمایه کسب‌وکارت در تاریکیِ انبار دود می‌شود؟! تا حالا به این فکر کرده‌اید که چرا با وجودِ فروشِ بالا، حسابِ بانکی‌تان آخرِ ماه خالی است؟! نشتی‌های پنهان، ضایعاتِ ثبت‌نشده و کسری‌های نامعلوم، بی‌صدا سودِ خالصِ شما را می‌بلعند!"
    ),
    (
        "scene_02",
        "کابوسِ تلخِ انبارگردانیِ سنتی! فروشگاه و کارخانه را سه روز تعطیل می‌کنید، کارگرها لیست‌به‌دست بینِ قفسه‌ها می‌چرخند و آخرش چی؟! صدها قلم مغایرتِ وحشتناک، کالاهایی که تاریخِ مصرفشان گذشته و در نهایت: هیچ‌کس نمی‌داند سودِ واقعی کجاست!"
    ),
    (
        "scene_03",
        "اما راهکارِ نپتون چیست؟! انبارداریِ زنده و متصل به حسابداری با فرمولِ دقیقِ میانگینِ موزونِ بهای تمام‌شده! با ورود یا خروجِ هر کالا، بهای ریالی در همان ثانیه محاسبه و سندِ دوبلِ حسابداری خودکار صادر می‌شود؛ بدونِ خطای انسانی و بدونِ تاخیرِ دفتری!"
    ),
    (
        "scene_04",
        "انتقالِ بینِ ده‌ها انبار در چند ثانیه، ثبتِ دقیقِ ضایعات با کدِ رهگیری، و اسکنِ بارکد بدونِ نیاز به متوقف کردنِ فروش! به محضِ رسیدنِ کالا به نقطه سفارش، نپتون خودکار هشدارِ کمبود می‌دهد تا هیچ مشتری دستِ خالی برنگردد!"
    ),
    (
        "scene_05",
        "قبل از نپتون: اضطرابِ انبارگردانی، کسری‌های نامعلوم و قیمت‌گذاری بر اساسِ حدس و گمان! بعد از نپتون: شفافیتِ ریالی تا آخرین قطعه محصول، کنترلِ لحظه‌ای انبار روی تلفنِ همراه و خوابِ راحتِ شبانه مدیران!"
    ),
    (
        "scene_06",
        "اجازه ندهید حتی یک ریال از سرمایه‌تان در انبار هدر برود! همین حالا با چهارده روز تستِ کاملاً رایگان، انبارتان را به هوشِ نپتون مجهز کنید یا زیرِ همین ویدیو، کلمه انبار را کامنت بگذارید: نپتون دات‌آی‌آر!"
    )
]

# 1. Generate Ganji (Primary Piper Voice - Decision D-138)
print("Generating Ganji Piper audios for Episode 3...")
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
print("Generating Amir Piper audios for Episode 3...")
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
    print("Generating Edge TTS audios for Episode 3...")
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
print("All Episode 3 audio tracks generated successfully!")
