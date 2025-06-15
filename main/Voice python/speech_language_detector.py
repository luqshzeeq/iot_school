import speech_recognition as sr
from langdetect import detect, DetectorFactory
from langdetect.lang_detect_exception import LangDetectException

# For consistent langdetect results (optional)
DetectorFactory.seed = 0

def identify_language_from_text(text_to_detect):
    """
    Detects the language of the given text using langdetect.
    """
    if not text_to_detect or not isinstance(text_to_detect, str) or text_to_detect.strip() == "":
        return "Error: Input text for language detection is empty."
    try:
        language_code = detect(text_to_detect)
        return language_code
    except LangDetectException:
        return "Error: Langdetect could not reliably detect language (text might be too short or ambiguous)."
    except Exception as e:
        return f"An unexpected error occurred in langdetect: {str(e)}"

def listen_and_detect_language():
    """
    Listens to microphone input, converts speech to text,
    and then detects the language of that text.
    """
    recognizer = sr.Recognizer()
    microphone = sr.Microphone()

    with microphone as source:
        print("\nAdjusting for ambient noise... Please wait.")
        recognizer.adjust_for_ambient_noise(source, duration=1)
        print("Listening... Please speak something!")

        try:
            audio = recognizer.listen(source, timeout=5, phrase_time_limit=10) # Listen for up to 5s, max 10s phrase
            print("Processing audio...")
        except sr.WaitTimeoutError:
            print("No speech detected within the time limit.")
            return

    if audio:
        try:
            # Using Google Web Speech API for Speech-to-Text
            # This requires an internet connection.
            print("Recognizing speech using Google Web Speech API...")
            recognized_text = recognizer.recognize_google(audio) # You can specify language e.g., recognize_google(audio, language="en-US")
            print(f"You said: \"{recognized_text}\"")

            # Now, detect the language of the recognized text
            if recognized_text:
                language_code = identify_language_from_text(recognized_text)
                print(f"Detected language code: {language_code}")
            else:
                print("Could not get text from speech to detect language.")

        except sr.UnknownValueError:
            print("Google Web Speech API could not understand the audio.")
        except sr.RequestError as e:
            print(f"Could not request results from Google Web Speech API; {e}")
        except Exception as e:
            print(f"An unexpected error occurred during speech recognition or language detection: {e}")

if __name__ == "__main__":
    print("Speech Language Detector Started.")
    print("Press Ctrl+C to exit.")
    try:
        while True:
            listen_and_detect_language()
            input("Press Enter to try again or Ctrl+C to exit...")
            print("-" * 30)
    except KeyboardInterrupt:
        print("\nExiting program.")