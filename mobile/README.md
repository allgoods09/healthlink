# HealthLink BHW Mobile

Expo-based companion app for BHW field work.

Current scope: Android only.

## What it does

- Login with the same verified BHW credentials used on the web app
- Enforce one active device token per BHW account
- Download only the households, residents, and visit history assigned to the BHW's purok
- Work from a local SQLite database
- Queue create and edit changes for households, residents, and household visit history
- Upload pending changes manually or automatically when internet is available
- Capture visit photos using the device camera only
- Switch between English and Cebuano

## Local setup

1. Copy `.env.example` to `.env` if you want a default API URL.
2. Set `EXPO_PUBLIC_API_BASE_URL` to your Laravel server URL using your computer's LAN IP, for example `http://192.168.1.8:8000`.
3. Run `npm install` if needed.
4. Start the app with `npm run android` for local/LAN access on a real phone, or `npm run android:tunnel` if your phone cannot reach your computer over Wi-Fi.
5. If you want to auto-open an Android emulator later, use `npm run android:emulator`.

## Notes

- When using a real phone, `localhost` and `127.0.0.1` will point to the phone itself, not your computer. Use your computer's LAN IP instead.
- Your Laravel server must listen on `0.0.0.0` or your LAN IP, not only on `127.0.0.1`.
- The app expects the Laravel backend in this repo to be running and reachable.
- This project currently targets Android only.
- The app currently uses Expo SDK 57. If the Play Store Expo Go build says the project is incompatible, install the matching Android Expo Go build for SDK 57 instead of the latest store build.
- You can fetch the correct Android build with `npx expo-go download android 57` or open the direct download URL:
  `https://github.com/expo/expo-go-releases/releases/download/Expo-Go-57.0.2/Expo-Go-57.0.2.apk`
