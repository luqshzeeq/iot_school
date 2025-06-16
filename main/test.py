import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
import csv
from lingua import Language, LanguageDetectorBuilder

# ===== CONFIGURATION =====

# Expected phrases per language
expected_phrases = {
    'MALAY': [
        "makcik nak order nasi lemak dan teh ais",
        "saya mahu nasi goreng dan air sirap",
        "saya nak beli roti canai dan milo ais"
    ],
    'ENGLISH': [
        "i would like to order nasi lemak and iced tea",
        "i want fried rice and syrup drink",
        "i want to buy roti canai and milo ice"
    ],
    'CHINESE': [
        "wo yao ji fan",
        "wo yao mai roti dan he milo bing",
        "ni hao wo yao cha dan ji fan"
    ],
    'TAMIL': [
        "vanakkam enakku soru vendum",
        "naan sappiduven",
        "soru kudunga milo kudunga"
    ]
}

# Google Speech API language codes
speech_lang_code = {
    'ENGLISH': 'en-US',
    'MALAY': 'ms-MY',
    'CHINESE': 'zh-CN',
    'TAMIL': 'ta-IN'
}

# Setup language detection (lingua)
language_map = {
    'ENGLISH': Language.ENGLISH,
    'MALAY': Language.MALAY,
    'CHINESE': Language.CHINESE,
    'TAMIL': Language.TAMIL
}
detector = LanguageDetectorBuilder.from_languages(*language_map.values()).build()

# Set target language based on day
day = datetime.now().strftime("%A")
if day in ['Monday', 'Tuesday']:
    target_language = 'MALAY'
elif day == 'Wednesday':
    target_language = 'ENGLISH'
elif day == 'Thursday':
    target_language = 'CHINESE'
elif day == 'Friday':
    target_language = 'TAMIL'
else:
    target_language = 'ENGLISH'  # default fallback

# ===== START PROCESS =====
print(f"📅 Today is {day}. Expected language: {target_language}")
print("🎤 Please speak your order...")

# Start mic
recognizer = sr.Recognizer()
with sr.Microphone() as source:
    recognizer.adjust_for_ambient_noise(source, duration=1)
    print("🎧 Listening...")
    audio = recognizer.listen(source)

try:
    # Transcribe audio
    text = recognizer.recognize_google(audio, language=speech_lang_code[target_language])
    print(f"📝 Transcribed: {text}")
    user_text = text.lower().strip()

    # Step 1: AI detect language
    detected_lang = detector.detect_language_of(user_text).name.upper()
    print(f"🌍 Detected language: {detected_lang}")

    # Step 2: AI match
    if detected_lang == target_language:
        feedback = f"✅ Correct! You spoke in {target_language.capitalize()}."
        result = "Correct"

    # Step 3: Fallback - expected phrase match
    elif any(phrase.lower() in user_text for phrase in expected_phrases[target_language]):
        feedback = f"✅ Acceptable phrase in {target_language.capitalize()}."
        result = "Correct (Fallback)"

    # Step 4: Fail
    else:
        feedback = f"❌ Wrong language. Please speak in {target_language.capitalize()}."
        result = "Wrong"

    # Audio response
    tts = gTTS(text=feedback, lang='en')
    tts.save("feedback.mp3")
    playsound("feedback.mp3")

    # Logging
    now = datetime.now()
    with open("canteen_log.csv", mode='a', newline='', encoding='utf-8') as file:
        writer = csv.writer(file)
        writer.writerow([
            now.strftime("%Y-%m-%d"),
            now.strftime("%H:%M:%S"),
            text,
            detected_lang,
            target_language,
            result
        ])
    print("✅ Logged to canteen_log.csv")

except sr.UnknownValueError:
    print("❌ Could not understand audio.")
    feedback = f"Sorry, I couldn’t understand. Please try again in {target_language.capitalize()}."
    tts = gTTS(text=feedback, lang='en')
    tts.save("feedback.mp3")
    playsound("feedback.mp3")

except Exception as e:
    print(f"❌ Error: {e}")