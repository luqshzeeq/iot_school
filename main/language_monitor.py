import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
from datetime import datetime
import csv
from lingua import Language, LanguageDetectorBuilder
import mysql.connector
import os
import requests
import sys

# Add these imports at the top of your file
import numpy as np
import noisereduce as nr

import io

# --- ADD THESE THREE LINES ---
# This forces the print output to be UTF-8, which can handle any language characters.
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')
# -----------------------------

# ===== DATABASE CONFIGURATION =====
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'language_monitor'
}

# ===== ESP32 DEVICE CONFIGURATION (for direct communication) =====
ESP32_IP_ADDRESS = "172.20.10.3"
ESP32_WEB_SERVER_PORT = 80

# ===== CONFIGURATION =====
expected_phrases = {
    'MALAY': [
        # --- Situation 1: Canteen / Ordering Food ---
        "makcik nak order nasi lemak dan teh ais", "saya mahu nasi goreng dan air sirap",
        "saya nak beli roti canai dan milo ais", "pakcik bagi saya nasi ayam satu",
        "kak saya nak mi goreng dan sirap bandung", "abang nak order maggi sup satu",
        "nasi lemak bungkus satu", "roti telur dua keping", "order teh o ais limau",
        "nak nasi goreng pattaya", "kopi o panas satu", "mihun goreng satu makan sini",
        "saya mahu makan roti telur dan kopi o", "boleh saya dapat nasi kandar",
        "bagi saya nasi putih dengan ayam goreng", "saya nak pesan karipap tiga biji",
        "kalau ada kuih saya nak dua", "nasi ni berapa ringgit", "makcik kira semua sekali",
        "terima kasih ini duitnya", "taknak pedas ya", "tambah telur mata satu",
        # --- Situation 2: Greetings & Basic Politeness ---
        "selamat pagi cikgu", "selamat pagi kawan-kawan", "terima kasih", "sama-sama",
        "jumpa lagi esok", "selamat tinggal", "maaf saya lambat", "minta maaf saya tak sengaja",
        "boleh saya tumpang tanya",
        # --- Situation 3: Classroom & School Life ---
        "cikgu boleh saya ke tandas", "cikgu saya sudah siap", "saya tidak faham bab ini",
        "boleh terangkan sekali lagi", "kerja sekolah muka surat berapa",
        "bila tarikh hantar kerja ini", "boleh saya pinjam pemadam",
        "awak dah siap kerja rumah", "jom pergi perpustakaan", "hari ini ada perhimpunan tak",
        # --- Situation 4: Asking for Help & Directions ---
        "tumpang tanya tandas di mana", "boleh tolong saya angkat buku ini",
        "pejabat sekolah di sebelah mana", "saya sesat boleh tunjukkan jalan ke kelas lima bestari",
        "awak tahu bilik guru di mana",
        # --- Situation 5: General Conversation ---
        "awak apa khabar", "saya sihat terima kasih", "awak dari mana", "jom main bola di padang",
        "petang ini ada aktiviti kokurikulum", "bas sekolah dah sampai ke belum", "nama saya ahmad",
    ],
    'ENGLISH': [
        # --- Situation 1: Canteen / Ordering Food ---
        "i would like to order nasi lemak and iced tea", "i want fried rice and a syrup drink",
        "i want to buy roti canai and milo ice", "can i have one chicken rice please",
        "i'll get the fried noodles and an iced lemon tea", "aunty i want to order one maggi soup",
        "one roti canai please", "one nasi lemak for takeaway", "two fried eggs and bread",
        "char kway teow one plate", "iced milo please", "i would like to have the fried rice to eat here",
        "could i please get a curry puff and a hot coffee", "i want to order nasi lemak with extra sambal",
        "give me one fried rice to go", "can i order one iced milo", "i want to eat here",
        "how much is this", "uncle please calculate everything", "thank you here is the money",
        "not spicy please",
        # --- Situation 2: Greetings & Basic Politeness ---
        "good morning teacher", "hello everyone", "thank you very much", "you're welcome",
        "see you tomorrow", "goodbye", "sorry i am late", "i'm sorry it was an accident",
        "excuse me can i ask something",
        # --- Situation 3: Classroom & School Life ---
        "teacher may i go to the toilet", "teacher i have finished", "i don't understand this chapter",
        "can you please explain it again", "what page is the homework on",
        "when is the submission date", "can i borrow your eraser", "have you done the homework",
        "let's go to the library", "is there an assembly today",
        # --- Situation 4: Asking for Help & Directions ---
        "excuse me where is the toilet", "can you help me carry these books",
        "which way is the school office", "i'm lost can you show me the way to class five bestari",
        "do you know where the staff room is",
        # --- Situation 5: General Conversation ---
        "how are you doing", "i'm fine thank you", "where are you from", "let's go play football on the field",
        "are there any club activities this evening", "has the school bus arrived yet", "my name is sarah",
    ],
    'CHINESE': [
        # --- Situation 1: Canteen / Ordering Food ---
        "wo yao ji fan", "lao ban wo yao mai roti canai", "ni hao wo yao cha shao ji fan",
        "wo yao yi ge ye jiang fan he ka fei bing", "an yi gei wo yi wan mian tang",
        "chao fan yi pan", "ka li mian da bao", "zhe li chi", "liang bei milo bing",
        "shao mai san ge", "wo xiang yao yi ge kao mian bao he re yin liao", "lao ban wo de chao fan bao qi lai",
        "wo yao mai yi ge ka li mian bao", "lao ban zhe ge duo shao qian", "zong gong duo shao qian",
        "lao ban suan qian", "xie xie ni zhe shi wo de qian", "bu yao fang la jiao",
        # --- Situation 2: Greetings & Basic Politeness ---
        "lao shi zao an", "da jia zao an", "xie xie ni", "bu ke qi", "ming tian jian", "zai jian",
        "dui bu qi wo chi dao le", "bu hao yi si",
        # --- Situation 3: Classroom & School Life ---
        "lao shi wo ke yi qu ce suo ma", "lao shi wo zuo wan le", "zhe yi课wo bu dong",
        "ke yi zai jiang yi ci ma", "gong ke zai di ji ye", "shen me shi hou jiao",
        "ke yi jie ni de xiang pi ca ma", "ni de gong ke zuo wan le ma", "wo men qu tu shu guan ba",
        # --- Situation 4: Asking for Help & Directions ---
        "qing wen ce suo zai na li", "ke yi bang wo na zhe xie shu ma", "ban gong shi zai na yi bian", "wo mi lu le",
        # --- Situation 5: General Conversation ---
        "ni hao ma", "wo hen hao xie xie", "ni zhu zai na li", "yi qi qu da qiu", "wo de ming zi shi ah beng",
    ],
    'TAMIL': [
        # --- Situation 1: Canteen / Ordering Food ---
        "vanakkam enakku nasi lemak vendum", "anna oru roti canai kudunga", "akka oru thosai podunga",
        "soru kudunga milo ais kudunga", "enakku oru teh ais", "oru mee goreng parcel", "kopi o ais onnu",
        "inge sappiduven", "oru maggi sup podunga", "roti telur rendu", "naan sappida poren",
        "enakku konjam sambal podunga", "enakku oru nalla sudaa kopi venum", "idhu evvalavu kaasu",
        "anna mottam evvalavu", "nandri idho kaasu", "oraipu vendam",
        # --- Situation 2: Greetings & Basic Politeness ---
        "vanakkam teacher", "vanakkam nanbargale", "romba nandri", "parava illa", "naalai santhipom",
        "poi varugiren", "mannikavum naan thaamadham aagivittathu", "manniyungal",
        # --- Situation 3: Classroom & School Life ---
        "teacher naan toilet poga-lama", "teacher naan mudithu vitten", "enakku indha paadam puriyavillai",
        "marubadiyum solla mudiyuma", "veetu paadam entha pakkam", "eppa kudukkanum", "un eraser kodu",
        "nee homework senjitiya", "vaanga library ku pogalam",
        # --- Situation 4: Asking for Help & Directions ---
        "toilet enge irukku", "indha book thookka udhavi seiringala", "office enge irukku",
        "naan vazhi marandhu vitten",
        # --- Situation 5: General Conversation ---
        "eppadi irukkinga", "naan nalla irukken nandri", "neenga enge irundhu varinga",
        "vaanga vilaiyada pogalam", "en peyar kumaran",
    ]
}

speech_lang_code = {
    'ENGLISH': 'en-US',
    'MALAY': 'ms-MY',
    'CHINESE': 'zh-CN',
    'TAMIL': 'ta-IN'
}

DB_LANG_TO_INTERNAL_KEY = {
    'Bahasa Melayu': 'MALAY',
    'English': 'ENGLISH',
    'Mandarin': 'CHINESE',
    'Tamil': 'TAMIL'
}

language_map = {
    'ENGLISH': Language.ENGLISH,
    'MALAY': Language.MALAY,
    'CHINESE': Language.CHINESE,
    'TAMIL': Language.TAMIL
}

# --- ADD THIS NEW DICTIONARY ---
REVERSE_LANGUAGE_MAP = {v: k for k, v in language_map.items()}
# -----------------------------

detector = LanguageDetectorBuilder.from_languages(*language_map.values()).build()

def play_feedback_sound(text, lang='en'):
    """Generates and plays an audio feedback, then cleans up the audio file."""
    try:
        tts = gTTS(text=text, lang=lang)
        audio_file = "feedback.mp3"
        tts.save(audio_file)
        # playsound(audio_file) # Uncomment this if you want to play feedback on the speaker
    except Exception as e:
        print(f"Error playing sound: {e}")
    finally:
        if os.path.exists(audio_file):
            os.remove(audio_file)

def get_db_connection():
    """Establishes and returns a database connection."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as err:
        print(f"DB ERROR: Error connecting to database: {err}")
        return None

def get_language_of_day_from_db(date_str):
    """
    Fetches the global language set for a given date from the database.
    Returns (language_id, language_name, internal_language_key) or (None, None, None) if not found.
    """
    conn = get_db_connection()
    if not conn:
        return None, None, None

    cursor = conn.cursor(dictionary=True)
    try:
        query = """
        SELECT tdl.language_id, l.language_name
        FROM teacher_daily_languages tdl
        JOIN languages l ON tdl.language_id = l.id
        WHERE tdl.setting_date = %s
        LIMIT 1
        """
        cursor.execute(query, (date_str,))
        result = cursor.fetchone()

        if result:
            target_language_id = result['language_id']
            target_language_name = result['language_name']
            internal_lang_key = DB_LANG_TO_INTERNAL_KEY.get(target_language_name)
            print(f"Found daily language: {target_language_name} (ID: {target_language_id})")
            return target_language_id, target_language_name, internal_lang_key
        else:
            print(f"No global language setting found for {date_str}.")
            return None, None, None
    except mysql.connector.Error as err:
        print(f"DB ERROR: Error fetching language of the day: {err}")
        return None, None, None
    finally:
        cursor.close()
        conn.close()

def log_language_usage_to_db(language_id, detected_lang_str, status, transcribed_text):
    """Logs the language usage details to the language_usage table."""
    conn = get_db_connection()
    if not conn:
        print("DB ERROR: Connection failed, cannot log language usage.")
        return

    cursor = conn.cursor()
    try:
        current_date = datetime.now().strftime("%Y-%m-%d")
        status_enum = 'correct' if status == 'Correct' or status == 'Correct (Fallback)' else 'incorrect'

        query = """
        INSERT INTO language_usage (language_id, usage_date, detected_language, status, timestamp)
        VALUES (%s, %s, %s, %s, NOW())
        """
        cursor.execute(query, (language_id, current_date, detected_lang_str, status_enum))
        conn.commit()
        print("Language usage logged to database (language_usage table).")
    except mysql.connector.Error as err:
        print(f"DB ERROR: Error logging language usage (language_usage table): {err}")
        conn.rollback()
    finally:
        cursor.close()
        conn.close()

def log_student_interaction_to_db(log_date, log_time, transcribed_text, detected_language, expected_language, result_status):
    """Logs student interaction details to the student_interaction_logs table."""
    conn = get_db_connection()
    if not conn:
        print("DB ERROR: Connection failed, cannot log student interaction.")
        return

    cursor = conn.cursor()
    try:
        query = """
        INSERT INTO student_interaction_logs (log_date, log_time, transcribed_text, detected_language, expected_language, result_status, timestamp)
        VALUES (%s, %s, %s, %s, %s, %s, NOW())
        """
        cursor.execute(query, (log_date, log_time, transcribed_text, detected_language, expected_language, result_status))
        conn.commit()
        print("Student interaction logged to database (student_interaction_logs table).")
    except mysql.connector.Error as err:
        print(f"DB ERROR: Error logging student interaction (student_interaction_logs table): {err}")
        conn.rollback()
    finally:
        cursor.close()
        conn.close()

def send_result_to_esp32(result_status_for_esp32):
    """Sends the detection result as an HTTP GET request to the ESP32."""
    if ESP32_IP_ADDRESS == "YOUR_ESP32_IP_ADDRESS":
        print("ERROR: ESP32_IP_ADDRESS is not set. Cannot send result to ESP32.")
        return

    try:
        url = f"http://{ESP32_IP_ADDRESS}:{ESP32_WEB_SERVER_PORT}/?result={result_status_for_esp32}"
        response = requests.get(url, timeout=5)
        print(f"Sent result '{result_status_for_esp32}' to ESP32. ESP32 Response: {response.status_code} - {response.text}")
    except requests.exceptions.RequestException as e:
        print(f"ERROR: Could not send result to ESP32 at {ESP32_IP_ADDRESS}: {e}")

# ===== START PROCESS =====
print("Script started. Time:", datetime.now().strftime('%Y-%m-%d %H:%M:%S'))

# Check for command-line arguments to get expected language for UI display
if len(sys.argv) > 1 and sys.argv[1] == '--get-expected-language':
    current_date_str_arg = datetime.now().strftime("%Y-%m-%d")
    _, target_language_name_for_display_arg, _ = get_language_of_day_from_db(current_date_str_arg)
    if target_language_name_for_display_arg:
        print(f"EXPECTED_LANGUAGE_IS: {target_language_name_for_display_arg}")
    else:
        print("EXPECTED_LANGUAGE_IS: Not Found")
    sys.exit(0)

# ===== NORMAL EXECUTION PATH =====
current_date_str = datetime.now().strftime("%Y-%m-%d")
target_language_id, target_language_name_for_display, target_language_internal_key = get_language_of_day_from_db(current_date_str)

if not target_language_internal_key:
    print("Could not determine target language. Exiting.")
    play_feedback_sound("Sorry, I cannot determine the language for today. Please contact support.")
    send_result_to_esp32("Unknown")
    sys.exit(1)

print(f"Today is {datetime.now().strftime('%A, %Y-%m-%d')}. Expected language: {target_language_name_for_display.capitalize()}")
print("\nPlease speak your order...")

recognizer = sr.Recognizer()
recognizer.energy_threshold = 700
recognizer.pause_threshold = 1.5

# ### NEW LOGIC ### Initialize variables for clarity
transcribed_text = "N/A"
detected_lang_name = "N/A"
result = "Wrong"
feedback_msg = ""
result_for_esp32 = "Unknown"
cleaned_audio = None

# ### NEW LOGIC ### Step 1: Capture and clean audio separately
try:
    with sr.Microphone(sample_rate=16000) as source:
        print("Listening...")
        audio_data = recognizer.listen(source, timeout=5, phrase_time_limit=15)
        
        print("Audio captured. Now cleaning noise...")
        audio_data_np = np.frombuffer(audio_data.get_raw_data(), dtype=np.int16)
        reduced_noise_audio = nr.reduce_noise(y=audio_data_np, sr=source.SAMPLE_RATE, prop_decrease=0.8)
        cleaned_audio = sr.AudioData(reduced_noise_audio.tobytes(), source.SAMPLE_RATE, source.SAMPLE_WIDTH)
        print("Noise cleaning complete. Processing...")

except sr.WaitTimeoutError:
    feedback_msg = "Sorry, I didn't hear anything. Please try again."
    result_for_esp32 = "NoSpeech"
    log_language_usage_to_db(target_language_id, "NO_SPEECH", "Wrong", "No speech detected within timeout")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), "NO_SPEECH_DETECTED", "N/A", target_language_internal_key, "NoSpeech")

except Exception as e:
    feedback_msg = "Microphone error. Please check your microphone and permissions."
    result_for_esp32 = "MicError"
    log_language_usage_to_db(target_language_id, "MIC_ERROR", "Wrong", f"Microphone error: {e}")
    log_student_interaction_to_db(datetime.now().strftime("%Y-%m-%d"), datetime.now().strftime("%H:%M:%S"), f"MIC_ERROR: {e}", "N/A", target_language_internal_key, "MicError")

# ===== NEW, MORE RELIABLE RECOGNITION LOGIC =====
if cleaned_audio:
    try:
        # --- Step 1: Get a transcription first, trying most likely languages ---
        transcribed_text = None
        # We prioritize English and Malay as they are most common for cross-language recognition
        language_priority_codes = ['en-US', 'ms-MY', 'ta-IN', 'zh-CN']
        
        print("Attempting to transcribe audio...")
        for lang_code in language_priority_codes:
            try:
                # Try to get text using a language model
                transcribed_text = recognizer.recognize_google(cleaned_audio, language=lang_code)
                if transcribed_text:
                    print(f"Successfully transcribed using model '{lang_code}'.")
                    break # Exit loop once we have a good transcription
            except sr.UnknownValueError:
                print(f"Audio not clearly understood as '{lang_code}', trying next model.")
                continue # Try the next language

        # --- Step 2: If we have text, detect its language using Lingua ---
        if transcribed_text:
            detected_lingua_lang = detector.detect_language_of(transcribed_text)
            
            if detected_lingua_lang is not None:
                detected_lang_key = REVERSE_LANGUAGE_MAP.get(detected_lingua_lang, "UNKNOWN")
                print(f"Lingua detected language of text as: {detected_lang_key}")

                # --- Step 3: Compare detected language with the expected language ---
                if detected_lang_key == target_language_internal_key:
                    result = "Correct"
                    result_for_esp32 = "Correct"
                    feedback_msg = f"Correct! You spoke in {target_language_name_for_display}."
                    print(f"Transcription (Correct): {transcribed_text}")
                else:
                    result = "Wrong"
                    result_for_esp32 = "Wrong"
                    feedback_msg = f"Wrong language. Detected {detected_lang_key}. Please speak in {target_language_name_for_display}."
                    print(f"Transcription (Incorrect): Spoke {detected_lang_key}, heard: '{transcribed_text}'")
            else:
                # Lingua couldn't determine the language, treat as unknown
                result = "Wrong"
                result_for_esp32 = "Unknown"
                detected_lang_key = "UNKNOWN"
                feedback_msg = f"Sorry, I couldn't determine the language you spoke. Please try speaking in {target_language_name_for_display}."
                print(f"Could not determine language of transcribed text: '{transcribed_text}'")

        else:
            # This block runs if transcription failed for ALL languages
            result = "Wrong"
            result_for_esp32 = "Unknown"
            detected_lang_key = "UNKNOWN"
            transcribed_text = "UNTRANSCRIBABLE"
            feedback_msg = f"Sorry, I couldn't understand what you said. Please try speaking in {target_language_name_for_display}."
            print("Could not understand audio in any of the supported languages.")

    except sr.RequestError as e:
        print(f"Could not request results from Google; {e}")
        transcribed_text = f"API_ERROR: {e}"
        detected_lang_key = "API_ERROR"
        feedback_msg = "Speech service error. Please check your internet connection."
        result_for_esp32 = "API_Error"
    except Exception as e:
        print(f"An unexpected error occurred during recognition: {e}")
        transcribed_text = f"SYSTEM_ERROR: {e}"
        detected_lang_key = "SYSTEM_ERROR"
        feedback_msg = "An unexpected system error occurred."
        result_for_esp32 = "SystemError"

    # --- Step 4: Log the final results to the database ---
    log_language_usage_to_db(target_language_id, detected_lang_key, result, transcribed_text)
    log_student_interaction_to_db(
        datetime.now().strftime("%Y-%m-%d"),
        datetime.now().strftime("%H:%M:%S"),
        transcribed_text,
        detected_lang_key,
        target_language_internal_key,
        result_for_esp32 # Log the status sent to ESP32
    )

# ### NEW LOGIC ### Step 4: Send the final feedback message (if any was generated)
if feedback_msg:
    print(f"\nFINAL FEEDBACK: {feedback_msg}")
    play_feedback_sound(feedback_msg, lang='en')
    send_result_to_esp32(result_for_esp32)

print("\nScript finished.")