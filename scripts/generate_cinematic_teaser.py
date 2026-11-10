import asyncio
import io
import os
import wave
import edge_tts
import piper

# ==============================================================================
# NEPTUNE CINEMATIC COMMERCIAL SCENARIO WITH CALIBRATED CLAUSE PAUSES & INFLECTIONS
# ==============================================================================
CINEMATIC_SCENES = [
    {
        'id': 'teaser_01',
        # Scene 1: Crisis & Urgency
        'clauses': [
            ('اینترنت قطع شده!', 0.35),
            ('صفِ مشتری‌ها تا دمِ در کشیده!', 0.35),
            ('فردا سررسیدِ چکِ همکاره، و مهلتِ دارایی هم داره تموم میشه!', 0.0)
        ],
        'full_text': 'اینترنت قطع شده!... صفِ مشتری‌ها تا دمِ در کشیده!... فردا سررسیدِ چکِ همکاره، و مهلتِ دارایی هم داره تموم میشه!'
    },
    {
        'id': 'teaser_02',
        # Scene 2: High Anxiety & Drama
        'clauses': [
            ('کابوسِ سوختنِ هارد، و پاک شدنِ حساب و کتابِ ده سال کاسبی...', 0.38),
            ('یا جریمه‌های سنگینِ مالیاتی...', 0.35),
            ('خواب رو از چشمت گرفته؟!', 0.0)
        ],
        'full_text': 'کابوسِ سوختنِ هارد، و پاک شدنِ حساب و کتابِ ده سال کاسبی... یا جریمه‌های سنگینِ مالیاتی... خواب رو از چشمت گرفته?!'
    },
    {
        'id': 'teaser_03',
        # Scene 3: Paradigm Shift & Solution
        'clauses': [
            ('دیگه وقتِ اینه که روش‌های بیست سال پیش رو بریزی دور!', 0.35),
            ('با نِپتون، مدیریتِ مالی رو بیار به قرنِ جدید!', 0.0)
        ],
        'full_text': 'دیگه وقتِ اینه که روش‌های بیست سال پیش رو بریزی دور!... با نِپتون، مدیریتِ مالی رو بیار به قرنِ جدید!'
    },
    {
        'id': 'teaser_04',
        # Scene 4: Offline Power & Velocity
        'clauses': [
            ('صد در صد آفلاین اول!', 0.35),
            ('حتی اگه کلِ اینترنتِ کشور قطع بشه،', 0.25),
            ('دخل و فروشگاهت یک ثانیه هم متوقف نمیشه!', 0.0)
        ],
        'full_text': 'صد در صد آفلاین اول!... حتی اگه کلِ اینترنتِ کشور قطع بشه، دخل و فروشگاهت یک ثانیه هم متوقف نمیشه!'
    },
    {
        'id': 'teaser_05',
        # Scene 5: Tax Independence
        'clauses': [
            ('ارسالِ مستقیم به سامانه‌ی مؤدیان؛', 0.32),
            ('بدونِ حتی یک ریال کارمزد دادن به شرکت‌های مُعتَمَد!', 0.0)
        ],
        'full_text': 'ارسالِ مستقیم به سامانه‌ی مؤدیان؛... بدونِ حتی یک ریال کارمزد دادن به شرکت‌های مُعتَمَد!'
    },
    {
        'id': 'teaser_06',
        # Scene 6: AI Assistant & 3-2-1 Security
        'clauses': [
            ('دستیارِ هوشِ مصنوعی، و امنیتِ سه، دو، یک...', 0.35),
            ('هر شب کلِ دیتابیسِ شرکتت، رمزنگاری‌شده و امن توی تلگرامِ خودت ذخیره میشه!', 0.0)
        ],
        'full_text': 'دستیارِ هوشِ مصنوعی، و امنیتِ سه، دو، یک... هر شب کلِ دیتابیسِ شرکتت، رمزنگاری‌شده و امن توی تلگرامِ خودت ذخیره میشه!'
    },
    {
        'id': 'teaser_07',
        # Scene 7: Triumph & Call to Action
        'clauses': [
            ('نِپتون، حسابدارِ همیشه‌بیدارِ کسب‌وکارِ تو!', 0.35),
            ('همین الان با تستِ رایگانِ چهارده روزه، از نِپتون دات‌آی‌آر دانلودش کن!', 0.0)
        ],
        'full_text': 'نِپتون، حسابدارِ همیشه‌بیدارِ کسب‌وکارِ تو!... همین الان با تستِ رایگانِ چهارده روزه، از نِپتون دات‌آی‌آر دانلودش کن!'
    }
]

os.makedirs('public/audio/teaser/amir', exist_ok=True)
os.makedirs('public/audio/teaser/ganji', exist_ok=True)
os.makedirs('public/audio/teaser/farid', exist_ok=True)
os.makedirs('public/audio/teaser/dilara', exist_ok=True)

# ==============================================================================
# 1. SYNTHESIZE PIPER (Amir & Ganji) WITH CLAUSE STITCHING & CALIBRATED SILENCE
# ==============================================================================
print("=== 1. Generating Piper (Amir & Ganji) with Emotion & Breath Pauses ===")
voice_amir = piper.PiperVoice.load('models/piper/fa_IR-amir-medium.onnx', 'models/piper/fa_IR-amir-medium.onnx.json')
voice_ganji = piper.PiperVoice.load('models/piper/fa_IR-ganji-medium.onnx', 'models/piper/fa_IR-ganji-medium.onnx.json')

# syn_config with increased noise_scale (0.78) and noise_w_scale (0.88) for emotional warmth and dynamic cadence
syn_config_amir = piper.config.SynthesisConfig(length_scale=0.92, noise_scale=0.78, noise_w_scale=0.88)
syn_config_ganji = piper.config.SynthesisConfig(length_scale=0.94, noise_scale=0.72, noise_w_scale=0.85)

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

for s in CINEMATIC_SCENES:
    sid = s['id']
    clauses = s['clauses']
    
    # Amir
    sz_amir = synthesize_clauses_to_wav(voice_amir, syn_config_amir, clauses, f"public/audio/teaser/amir/{sid}.wav")
    print(f"Amir {sid}: {sz_amir} bytes")
    
    # Ganji
    sz_ganji = synthesize_clauses_to_wav(voice_ganji, syn_config_ganji, clauses, f"public/audio/teaser/ganji/{sid}.wav")
    print(f"Ganji {sid}: {sz_ganji} bytes")

# ==============================================================================
# 2. SYNTHESIZE MICROSOFT NEURAL (Farid & Dilara) WITH NATURAL DRAMATIC PROSODY
# ==============================================================================
print("\n=== 2. Generating Microsoft Neural (Farid & Dilara) with Emotional Prosody ===")
async def generate_neural():
    for s in CINEMATIC_SCENES:
        sid = s['id']
        txt = s['full_text']
        
        # Farid: Energetic commercial ad delivery with punch
        c_farid = edge_tts.Communicate(txt, 'fa-IR-FaridNeural', rate='+6%', pitch='+1Hz')
        await c_farid.save(f"public/audio/teaser/farid/{sid}.mp3")
        
        # Dilara: Warm, fluent storytelling with natural breath pauses
        c_dilara = edge_tts.Communicate(txt, 'fa-IR-DilaraNeural', rate='+3%', pitch='+0Hz')
        await c_dilara.save(f"public/audio/teaser/dilara/{sid}.mp3")
        print(f"Neural {sid} generated.")

asyncio.run(generate_neural())
print("\n=== ALL 4 CINEMATIC SUITES GENERATED SUCCESSFULLY! ===")
