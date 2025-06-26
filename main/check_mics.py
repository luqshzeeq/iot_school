import speech_recognition as sr

# Get a list of all available microphone names and their index
mic_list = sr.Microphone.list_microphone_names()

print("Available Microphones:")
# Loop through the list and print each microphone with its index number
for index, name in enumerate(mic_list):
    print(f"Index {index}: {name}")