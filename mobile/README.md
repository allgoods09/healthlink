# HealthLink BHW Mobile

Expo-based companion app for BHW field work.

Current scope: Android only.

## What it does

- Login with the same verified BHW credentials used on the web app
- Enforce one active device token per BHW account
- Download an authorized offline dataset; current official Resident browsing is assigned-purok-only
- Work from a local SQLite database
- Queue create and edit changes for households, residents, and household visit history
- Require a blocking first-time initial sync before the app can be used
- Keep all future app launches local-first, even when offline
- Upload pending changes manually first, then download fresh server data only when there are zero pending local changes
- Capture visit photos using the device camera only
- Switch between English and Cebuano

## Resident directory

- Directory opens with Residents and Households cards. Their counts are current official Residents and official nondeleted Households in the assigned purok; vacant Households still count. Requests never inflate these counts.
- Residents supports offline multi-token name/household-number search, Sex/Age group/Household filters, and deterministic name, age, or natural household sorting. More records load on scroll.
- Add Resident Request saves a local request. Manual sync submits it for Secretary verification; it does not immediately create an official registry record.
- Request Update proposes a correction to an official Resident. An update under review blocks another submitted correction. Secretary approval remains authoritative.
- Requests is separate from the registry. Its badge counts awaiting-upload and submitted items, not completed outcomes. The device shows saved new requests and the latest available correction state, not the complete server audit history.
- Rejected new requests remain viewable with their reason, without a Revise & Resubmit action. Safe linked replacement remains deferred.
- Resident Details groups identity, household, contact, demographics and request status. Assess PhilPEN is an entry point for eligible current official residents aged 20+, and resumes an unsynced assessment when present.
- Offline records reflect the last compatible download, not a fresh server decision. Sync when connected to check assignment, approval and registry changes.
- Household browsing/details/visits retain their existing workflow. The assigned-purok Household card count is not a count of every cached household/request in that existing list.

## Resident entry and profile contract

- Add Resident Request and Request Update use one four-step form: Household & Identity, Personal & Contact, Education & Livelihood, and Socio-Economic Information. Back/Next do not save; the review sheet's Save on device action saves locally. Submission still requires manual sync and Secretary approval.
- New requests and ordinary corrections select an existing official Household in the assigned purok, including vacant/headless Households. Create and approve a Household separately, then sync before adding its Residents. There is no Resident-to-Household creation shortcut.
- Previously queued Household+Resident packages retain their exact pending parent and hidden head proposal where applicable; this compatibility does not offer other pending Households for new work.
- PhilSys and the existing education/livelihood/socio-economic fields are captured, reviewed, and corrected through the same request. Finite profile choices come from the server. Household address remains read-only context.
- Resident download contract version 2 includes authoritative socio-economic values. Old downloaded official profiles require a successful refresh before full-profile editing. The additive SQLite upgrade preserves identities, revisions and pending work; unknown old fields are not submitted as clears. Older queued work can still upload before the compatible refresh.
- Nullable text can be deliberately cleared. The authoritative enum/flag columns remain non-null; an absent profile is shown as Not recorded, not manufactured as a known value. PWD No intentionally clears disability type.
- Deploy the additive ResidentDraft migration and matching server/mobile release together. No new lifecycle controls or full L8 support are included.

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
