from langdetect import detect, DetectorFactory
from langdetect.lang_detect_exception import LangDetectException

# To ensure consistent results for short texts, you can seed the detector
# For different results on each run for ambiguous short text, comment this line out
DetectorFactory.seed = 0

def identify_language(text_to_detect):
    """
    Detects the language of the given text.
    Returns the language code (e.g., 'en' for English, 'ms' for Malay)
    or an error message if detection fails.
    """
    if not text_to_detect or not isinstance(text_to_detect, str) or text_to_detect.strip() == "":
        return "Error: Input text is empty or invalid."

    try:
        language_code = detect(text_to_detect)
        return language_code
    except LangDetectException:
        # This exception can occur if the text is too short or ambiguous
        return "Error: Could not reliably detect language (text might be too short or ambiguous)."
    except Exception as e:
        return f"An unexpected error occurred: {str(e)}"

if __name__ == "__main__":
    # Example Usage
    text1 = "Hello, how are you today?"
    text2 = "Apa khabar semua?"
    text3 = "你好，世界！" # Ni hao, shijie! (Chinese)
    text4 = "வணக்கம் உலகம்" # Vanakkam Ulagam (Tamil)
    text5 = "Esto es una prueba." # Spanish
    text6 = "12345" # Ambiguous / Not a language
    text7 = "" # Empty text

    print(f"Text: \"{text1}\" \nDetected Language Code: {identify_language(text1)}\n")
    print(f"Text: \"{text2}\" \nDetected Language Code: {identify_language(text2)}\n")
    print(f"Text: \"{text3}\" \nDetected Language Code: {identify_language(text3)}\n")
    print(f"Text: \"{text4}\" \nDetected Language Code: {identify_language(text4)}\n")
    print(f"Text: \"{text5}\" \nDetected Language Code: {identify_language(text5)}\n")
    print(f"Text: \"{text6}\" \nDetected Language Code: {identify_language(text6)}\n")
    print(f"Text: \"{text7}\" \nDetected Language Code: {identify_language(text7)}\n")

    # Example with user input (uncomment to try)
    # try:
    #     user_text = input("Enter text to detect its language: ")
    #     detected_lang = identify_language(user_text)
    #     print(f"Detected Language Code for your input: {detected_lang}")
    # except KeyboardInterrupt:
    #     print("\nExiting...")