# HealthLink BHW Mobile

Expo-based companion app for BHW field work.

Current scope: Android only.

## What it does

- Login with the same verified BHW credentials used on the web app
- Enforce one active device token per BHW account
- Download the verified household, resident, and visit data for the whole assigned barangay
- Work from a local SQLite database
- Queue create and edit changes for households, residents, and household visit history
- Require a blocking first-time initial sync before the app can be used
- Keep all future app launches local-first, even when offline
- Upload pending changes manually first, then download fresh server data only when there are zero pending local changes
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

## Android APK build

1. Sign in to Expo/EAS:
   `npx eas-cli login`
2. Create the installable Android APK:
   `npm run build:apk`
3. After the build finishes, publish it in one of these two ways:
   - download the APK and place it at `storage/app/mobile-builds/healthlink-bhw-android.apk`
   - set `BHW_MOBILE_APK_URL` in the Laravel `.env` file to the hosted release URL
4. Open the BHW portal and use the `Download App` page to distribute the release.
