import { Ionicons } from '@expo/vector-icons';
import { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { CameraView, useCameraPermissions } from 'expo-camera';
import * as ImagePicker from 'expo-image-picker';
import React, { useEffect, useRef, useState } from 'react';
import { usePreventRemove } from '@react-navigation/native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Alert,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { SelectionBottomSheet } from '../components/SelectionBottomSheet';
import { HouseholdState } from '../components/HouseholdUi';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { useKeyboardAwareScroll } from '../hooks/useKeyboardAwareScroll';
import { i18n } from '../i18n';
import {
  formatFriendlyDate,
  formatFriendlyTime,
} from '../lib/format';
import { findHouseholdByReference } from '../lib/householdIdentity';
import {
  getHouseholdByLocalId,
  getVisitHouseholdOptions,
  getVisitByLocalId,
  saveVisit,
  hasHouseholdData,
} from '../lib/storage';
import { householdPage, HOUSEHOLD_PAGE_SIZE } from '../lib/householdPresentation';
import { createSaveGuard } from '../lib/residentWorkflow';
import { AppTheme } from '../theme';
import { HouseholdRecord, VisitPhoto } from '../types';

import { useLocalEditor } from '../lib/useLocalEditor';

export function VisitFormScreen({ route, navigation }: any) {
  useLocalEditor();
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  const insets = useSafeAreaInsets();
  const cameraRef = useRef<CameraView | null>(null);
  const { user, assignment, dataVersion, bumpDataVersion, requestConfirmation } = useAppContext();
  const { handleInputFocus, handleScroll, keyboardInset, scrollRef } =
    useKeyboardAwareScroll();
  const [permission, requestPermission] = useCameraPermissions();
  const [mediaPermission, requestMediaPermission] = ImagePicker.useMediaLibraryPermissions();
  const [households, setHouseholds] = useState<HouseholdRecord[]>([]);
  const [chooserVisible, setChooserVisible] = useState(false);
  const [cameraVisible, setCameraVisible] = useState(false);
  const [selectedHousehold, setSelectedHousehold] = useState<HouseholdRecord | null>(null);
  const [localId, setLocalId] = useState<number | null>(null);
  const [serverId, setServerId] = useState<number | null>(null);
  const [mobileUuid, setMobileUuid] = useState<string | null>(null);
  const [visitedAt, setVisitedAt] = useState(() => new Date());
  const [notes, setNotes] = useState('');
  const [photos, setPhotos] = useState<VisitPhoto[]>([]);
  const [formError, setFormError] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [saving, setSaving] = useState(false); const [saved, setSaved] = useState(false);
  const [photoBusy, setPhotoBusy] = useState(false); const [retry, setRetry] = useState(0); const [loadError, setLoadError] = useState(false);
  const [search, setSearch] = useState(''); const [query, setQuery] = useState(''); const [limit, setLimit] = useState(HOUSEHOLD_PAGE_SIZE);
  const guard = useRef(createSaveGuard()); const photoGuard = useRef(createSaveGuard());
  const savedRef = useRef(false);
  const operationBusy = useRef(false); const cameraEpoch = useRef(0);
  function closeCamera() { cameraEpoch.current++; setCameraVisible(false); }
  const fingerprint = JSON.stringify([selectedHousehold?.local_id ?? null, visitedAt.toISOString(), notes, photos]);
  const original = useRef(fingerprint); const dirty = fingerprint !== original.current;
  const dirtyRef = useRef(dirty); dirtyRef.current = dirty;
  const scope = `${user?.id}:${assignment?.barangay?.id}:${assignment?.purok?.id}`;
  const loadedScope = useRef(''); const live = useRef(true); const generation = useRef(0);
  const enabled = ready && loadedScope.current === scope && !saving && !saved && !photoBusy;
  const chooserPage = householdPage(households, 'operational', query, limit);
  usePreventRemove((dirty || saving || photoBusy) && !saved, ({ data }) => {
    if (saving || photoBusy) return;
    void requestConfirmation({ title: i18n.t('hhLeaveTitle'), message: i18n.t('hhLeaveBody'), confirmLabel: i18n.t('hhLeave') })
      .then(confirmed => { if (confirmed && live.current) navigation.dispatch(data.action); });
  });
  useEffect(() => { if (saved) navigation.goBack(); }, [saved, navigation]);
  useEffect(() => { const timer = setTimeout(() => { setQuery(search); setLimit(HOUSEHOLD_PAGE_SIZE); }, 300); return () => clearTimeout(timer); }, [search]);

  useEffect(() => {
    live.current = true; const token = ++generation.current; let applicable = true;
    setSaving(false); setPhotoBusy(false);
    setChooserVisible(false); closeCamera(); setReady(false); setLoadError(false);
    if (dirtyRef.current) { setFormError(i18n.t('hhRefresh')); return () => { applicable = false; live.current = false; }; }
    setFormError(null);
    async function loadExisting() {
      try {
        if (!await hasHouseholdData({ userId: user?.id, barangayId: assignment?.barangay?.id, purokId: assignment?.purok?.id })) { if (applicable) setFormError(i18n.t('hhRefresh')); return; }
        const options = await getVisitHouseholdOptions();
        const existing = route.params?.localId != null ? await getVisitByLocalId(route.params.localId) : null;
        const home = existing ? findHouseholdByReference(options, existing) : route.params?.householdLocalId != null ? await getHouseholdByLocalId(route.params.householdLocalId) : null;
        if (!applicable || token !== generation.current) return;
        setHouseholds(options);
        if ((route.params?.localId != null && !existing) || ((route.params?.localId != null || route.params?.householdLocalId != null) && !home)) {
          setFormError(i18n.t('hhVisitUnavailable')); return;
        }
        const date = existing ? new Date(existing.visited_at) : visitedAt;
        if (Number.isNaN(date.getTime())) { setFormError(i18n.t('hhVisitUnavailable')); return; }
        const nextNotes = existing?.notes ?? ''; const nextPhotos = existing?.photos ?? [];
        original.current = JSON.stringify([home?.local_id ?? null, date.toISOString(), nextNotes, nextPhotos]);
        loadedScope.current = scope; setSelectedHousehold(home); setLocalId(existing?.local_id ?? null); setServerId(existing?.server_id ?? null);
        setMobileUuid(existing?.mobile_uuid ?? null); setVisitedAt(date); setNotes(nextNotes); setPhotos(nextPhotos); setReady(true);
      } catch { if (applicable) { setFormError(i18n.t('savedRecordsError')); setLoadError(true); } }
    }
    void loadExisting(); return () => { applicable = false; live.current = false; };
  }, [route.params?.householdLocalId, route.params?.localId, scope, dataVersion, retry]);

  function appendPhoto(photo: VisitPhoto) {
    setPhotos((current) => [...current, photo]);
  }

  function openVisitDatePicker() {
    if (!enabled) return;
    const token = generation.current;
    DateTimePickerAndroid.open({
      value: visitedAt,
      mode: 'date',
      onChange: (event, selectedDate) => {
        if (!live.current || token !== generation.current || event.type !== 'set' || !selectedDate) {
          return;
        }

        setVisitedAt((current) => {
          const next = new Date(current);
          next.setFullYear(
            selectedDate.getFullYear(),
            selectedDate.getMonth(),
            selectedDate.getDate()
          );

          return next;
        });
      },
    });
  }

  function openVisitTimePicker() {
    if (!enabled) return;
    const token = generation.current;
    DateTimePickerAndroid.open({
      value: visitedAt,
      mode: 'time',
      is24Hour: false,
      onChange: (event, selectedDate) => {
        if (!live.current || token !== generation.current || event.type !== 'set' || !selectedDate) {
          return;
        }

        setVisitedAt((current) => {
          const next = new Date(current);
          next.setHours(selectedDate.getHours(), selectedDate.getMinutes(), 0, 0);

          return next;
        });
      },
    });
  }

  async function handlePickFromGallery() {
    if (!enabled || operationBusy.current || !photoGuard.current.acquire()) return;
    operationBusy.current = true; const token = generation.current; setPhotoBusy(true);
    try {
    if (!mediaPermission?.granted) {
      const result = await requestMediaPermission();

      if (!result.granted) {
        Alert.alert(i18n.t('uploadFromGallery'), i18n.t('galleryPermissionBody'));
        return;
      }
    }

    if (!live.current || token !== generation.current) return;
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsMultipleSelection: false,
      quality: 0.6,
      base64: true,
    });

    if (!live.current || token !== generation.current || result.canceled || result.assets.length === 0) {
      return;
    }

    const asset = result.assets[0];

    if (!asset.base64) throw new Error('missing image');

    appendPhoto({
      uri: asset.uri,
      base64: asset.base64,
      file_name: asset.fileName ?? `visit-gallery-${Date.now()}.jpg`,
      mime_type: asset.mimeType ?? 'image/jpeg',
      file_size_bytes: asset.fileSize ?? null,
      captured_at: new Date().toISOString(),
    });
    } catch { if (live.current && token === generation.current) setFormError(i18n.t('hhPhotoError')); }
    finally { photoGuard.current.release(); operationBusy.current = false; if (live.current && token === generation.current) setPhotoBusy(false); }
  }

  async function handleTakePhoto() {
    if (!enabled || operationBusy.current || !photoGuard.current.acquire()) return;
    operationBusy.current = true; const token = generation.current; setPhotoBusy(true);
    try {
    if (!permission?.granted) {
      const result = await requestPermission();
      if (!result.granted) {
        if (live.current && token === generation.current) setFormError(i18n.t('hhCameraPermission'));
        return;
      }
    }

    if (live.current && token === generation.current) setCameraVisible(true);
    } catch { if (live.current && token === generation.current) setFormError(i18n.t('hhPhotoError')); }
    finally { photoGuard.current.release(); operationBusy.current = false; if (live.current && token === generation.current) setPhotoBusy(false); }
  }

  async function capturePhoto() {
    if (!enabled || operationBusy.current || !photoGuard.current.acquire()) return;
    operationBusy.current = true; const token = generation.current; const cameraToken = cameraEpoch.current; setPhotoBusy(true);
    try {
    const photo = await cameraRef.current?.takePictureAsync({
      base64: true,
      quality: 0.5,
    });

    if (!live.current || token !== generation.current || cameraToken !== cameraEpoch.current) return;
    if (!photo?.base64) throw new Error('missing image');

    appendPhoto({
      uri: photo.uri,
      base64: photo.base64,
      file_name: `visit-${Date.now()}.jpg`,
      mime_type: 'image/jpeg',
      captured_at: new Date().toISOString(),
    });
    setCameraVisible(false);
    } catch { if (live.current && token === generation.current) setFormError(i18n.t('hhPhotoError')); }
    finally { photoGuard.current.release(); operationBusy.current = false; if (live.current && token === generation.current) setPhotoBusy(false); }
  }

  async function removePhoto(index: number) {
    if (!enabled || operationBusy.current || !photoGuard.current.acquire()) return;
    operationBusy.current = true; const token = generation.current; setPhotoBusy(true);
    try {
    const confirmed = await requestConfirmation({
      title: i18n.t('removePhotoConfirmationTitle'),
      message: i18n.t('removePhotoConfirmationBody'),
      confirmLabel: i18n.t('removePhotoConfirmationLabel'),
      tone: 'danger',
    });

    if (!confirmed || !live.current || token !== generation.current) {
      return;
    }

    setPhotos((current) => current.filter((_, photoIndex) => photoIndex !== index));
    } catch { if (live.current && token === generation.current) setFormError(i18n.t('hhPhotoError')); }
    finally { photoGuard.current.release(); operationBusy.current = false; if (live.current && token === generation.current) setPhotoBusy(false); }
  }

  async function handleSave() {
    if (!enabled || savedRef.current || operationBusy.current || !selectedHousehold || !guard.current.acquire()) return;
    operationBusy.current = true; const token = generation.current; setSaving(true); setFormError(null);
    try {
    const confirmed = await requestConfirmation({
      title: i18n.t('saveVisitConfirmationTitle'),
      message: i18n.t('saveVisitConfirmationBody'),
      confirmLabel: i18n.t('save'),
    });

    if (!confirmed || !live.current || token !== generation.current) {
      return;
    }

    await saveVisit({
      local_id: localId ?? undefined,
      server_id: serverId,
      mobile_uuid: mobileUuid,
      household_server_id: selectedHousehold.server_id ?? null,
      household_mobile_uuid: selectedHousehold.mobile_uuid ?? null,
      visited_at: visitedAt.toISOString(),
      notes,
      photos,
    }, user?.id);
    if (!live.current || token !== generation.current) return;
    savedRef.current = true; original.current = fingerprint; setSaved(true);
    bumpDataVersion();
    } catch { if (live.current && token === generation.current) setFormError(i18n.t('hhVisitSaveError')); }
    finally { guard.current.release(); operationBusy.current = false; if (live.current && token === generation.current) setSaving(false); }
  }

  if (loadedScope.current && loadedScope.current !== scope) return <KeyboardShiftView style={styles.screen}>
    <ScrollView contentContainerStyle={styles.content}><HouseholdState message={i18n.t('hhRefresh')} /></ScrollView>
  </KeyboardShiftView>;

  return (
    <KeyboardShiftView style={styles.screen}>
      <ScrollView
        ref={scrollRef}
        style={styles.screen}
        contentContainerStyle={[
          styles.content,
          { paddingBottom: theme.spacing.xl + keyboardInset },
        ]}
        keyboardShouldPersistTaps="handled"
        onScroll={handleScroll}
        scrollEventThrottle={16}
      >
        {!ready ? <HouseholdState message={formError ?? i18n.t('loading')} error={loadError} retry={loadError ? () => setRetry(value => value + 1) : undefined} /> : null}
        <View style={styles.card}>
          <Text style={styles.label}>{i18n.t('chooseHousehold')}</Text>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('chooseHousehold')} accessibilityState={{ disabled: !enabled }} disabled={!enabled}
            onPress={() => setChooserVisible(true)} style={styles.pickerButton}>
            <Text style={styles.pickerLabel}>
              {selectedHousehold?.household_no ?? i18n.t('chooseHousehold')}
            </Text>
          </Pressable>
          {selectedHousehold && loadedScope.current === scope ? <View>
            <Text style={styles.photoNote}>{selectedHousehold.household_address}</Text>
            <Text style={styles.photoNote}>{selectedHousehold.purok_display_name ?? assignment?.purok?.display_name ?? i18n.t('purokNotAvailable')}</Text>
          </View> : null}

        <Text style={styles.label}>{i18n.t('visitedAt')}</Text>
        <View style={styles.dateRow}>
          <View style={styles.dateFieldBlock}>
            <Text style={styles.secondaryLabel}>{i18n.t('visitDate')}</Text>
            <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('visitDate')} accessibilityState={{ disabled: !enabled }} disabled={!enabled} onPress={openVisitDatePicker} style={[styles.input, styles.dateSelector]}>
              <Text style={styles.dateSelectorText}>
                {formatFriendlyDate(visitedAt.toISOString()) ?? i18n.t('birthDatePlaceholder')}
              </Text>
            </Pressable>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('openCalendar')} disabled={!enabled} accessibilityState={{ disabled: !enabled }} onPress={openVisitDatePicker} style={styles.calendarButton}>
            <Text style={styles.calendarButtonText}>{i18n.t('openCalendar')}</Text>
          </Pressable>
        </View>

        <View style={styles.dateRow}>
          <View style={styles.dateFieldBlock}>
            <Text style={styles.secondaryLabel}>{i18n.t('visitTime')}</Text>
            <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('visitTime')} accessibilityState={{ disabled: !enabled }} disabled={!enabled} onPress={openVisitTimePicker} style={[styles.input, styles.dateSelector]}>
              <Text style={styles.dateSelectorText}>
                {formatFriendlyTime(visitedAt.toISOString()) ?? '--:--'}
              </Text>
            </Pressable>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('openClock')} accessibilityState={{ disabled: !enabled }} disabled={!enabled} onPress={openVisitTimePicker} style={styles.calendarButton}>
            <Text style={styles.calendarButtonText}>{i18n.t('openClock')}</Text>
          </Pressable>
        </View>

        <Text nativeID="visit-notes-label" style={styles.label}>{i18n.t('notes')}</Text>
        <TextInput
          accessibilityLabel={i18n.t('notes')} accessibilityLabelledBy="visit-notes-label" editable={enabled}
          value={notes}
          onChangeText={setNotes}
          onFocus={handleInputFocus}
          style={[styles.input, styles.multiline]}
          multiline
        />

        <Text style={styles.photoNote}>{i18n.t('photoIntegrityNote')}</Text>

        <View style={styles.photoActionRow}>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('takePhoto')} accessibilityState={{ disabled: !enabled }} disabled={!enabled} onPress={handleTakePhoto} style={[styles.secondaryButton, styles.photoActionButton]}>
            <Text style={styles.secondaryButtonText}>
              {photos.length > 0 ? i18n.t('addAnotherPhoto') : i18n.t('takePhoto')}
            </Text>
          </Pressable>
          <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('uploadFromGallery')} accessibilityState={{ disabled: !enabled }} disabled={!enabled} onPress={handlePickFromGallery} style={[styles.secondaryButton, styles.photoActionButton]}>
            <Text style={styles.secondaryButtonText}>{i18n.t('uploadFromGallery')}</Text>
          </Pressable>
        </View>

          <View style={styles.photoGrid}>
            {photos.map((photo, index) => (
              <View key={`${photo.file_name}-${index}`} style={styles.photoCard}>
                <Pressable
                  accessibilityRole="button" accessibilityLabel={i18n.t('hhPhotoRemove', { number: index + 1 })} accessibilityState={{ disabled: !enabled }} disabled={!enabled}
                  onPress={() => void removePhoto(index)}
                  style={styles.removePhotoButton}
                  hitSlop={10}
                >
                  <Ionicons name="close" size={14} color={theme.colors.textOnBrand} />
                </Pressable>
                {photo.uri ? (
                  <Image accessible accessibilityLabel={i18n.t('hhPhotoLabel', { number: index + 1 })} source={{ uri: photo.uri }} style={styles.photo} />
                ) : (
                  <View style={styles.photoPlaceholder}>
                    <Text style={styles.photoPlaceholderText}>{i18n.t('syncedPhotoLabel')}</Text>
                  </View>
                )}
              </View>
            ))}
          </View>
        </View>

        {ready && formError ? <Text accessibilityRole="alert" accessibilityLiveRegion="polite" style={{ color: theme.colors.danger }}>{formError}</Text> : null}
        <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('save')} accessibilityState={{ disabled: !enabled || !selectedHousehold }} disabled={!enabled || !selectedHousehold} onPress={handleSave} style={[styles.primaryButton, (!enabled || !selectedHousehold) && { opacity: 0.55 }]}>
          <Text style={styles.primaryButtonText}>{i18n.t(saving || photoBusy ? 'loading' : 'save')}</Text>
        </Pressable>

        <SelectionBottomSheet title={i18n.t('chooseHousehold')} visible={chooserVisible && enabled} selectedValue={String(selectedHousehold?.local_id ?? '')}
          onClose={() => setChooserVisible(false)} onSelect={value => { if (enabled) setSelectedHousehold(households.find(row => String(row.local_id) === value) ?? null); }}
          choices={chooserPage.rows.map(row => ({ value: String(row.local_id), label: row.household_no,
            description: [row.household_address, row.purok_display_name].filter(Boolean).join(' - ') }))}
          empty={<Text style={styles.photoNote}>{i18n.t(query.trim() ? 'hhNoMatches' : 'hhEmpty_operational')}</Text>}
          header={<View><TextInput accessibilityLabel={i18n.t('hhSearch')} value={search} onChangeText={setSearch} placeholder={i18n.t('hhSearchHint')}
            placeholderTextColor={theme.colors.placeholder} style={styles.input} />
            {chooserPage.rows.length < chooserPage.total ? <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('hhShowMore')} onPress={() => setLimit(value => value + HOUSEHOLD_PAGE_SIZE)} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>{i18n.t('hhShowMore')}</Text></Pressable> : null}</View>} />

        <Modal visible={cameraVisible && ready && loadedScope.current === scope} animationType="slide" onRequestClose={closeCamera}>
          <View style={styles.cameraScreen}>
            <CameraView ref={cameraRef} style={styles.camera} facing="back" />
            {formError ? <Text accessibilityRole="alert" style={{ color: theme.colors.danger }}>{formError}</Text> : null}
            <View style={[styles.cameraBar, { paddingBottom: Math.max(insets.bottom, theme.spacing.md) }]}>
              <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('cancel')} onPress={closeCamera} style={styles.cameraButton}>
                <Text style={styles.cameraButtonText}>{i18n.t('cancel')}</Text>
              </Pressable>
              <Pressable accessibilityRole="button" accessibilityLabel={i18n.t('takePhoto')} accessibilityState={{ disabled: !enabled }} disabled={!enabled} onPress={capturePhoto} style={styles.cameraButtonPrimary}>
                <Text style={styles.cameraButtonPrimaryText}>{i18n.t('takePhoto')}</Text>
              </Pressable>
            </View>
          </View>
        </Modal>
      </ScrollView>
    </KeyboardShiftView>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: { flex: 1, backgroundColor: theme.colors.background },
  content: { padding: theme.spacing.md, gap: theme.spacing.md },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
  },
  label: {
    color: theme.colors.text,
    fontWeight: '600',
    marginBottom: 8,
    marginTop: 10,
  },
  input: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.inputBackground,
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: theme.colors.text,
  },
  dateRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'flex-end',
    gap: theme.spacing.sm,
    marginTop: 10,
  },
  dateFieldBlock: {
    flex: 1,
    minWidth: 140,
  },
  secondaryLabel: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontWeight: '600',
    marginBottom: 8,
  },
  dateSelector: {
    justifyContent: 'center',
  },
  dateSelectorText: {
    color: theme.colors.text,
  },
  calendarButton: {
    minHeight: 48,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.primarySoft,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 14,
    paddingVertical: 14,
    minWidth: 112,
  },
  calendarButtonText: {
    color: theme.colors.primaryDark,
    fontWeight: '700',
    textAlign: 'center',
  },
  multiline: {
    minHeight: 120,
    textAlignVertical: 'top',
  },
  pickerButton: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.inputBackground,
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  pickerLabel: {
    color: theme.colors.text,
  },
  photoNote: {
    marginTop: 12,
    color: theme.colors.textMuted,
    lineHeight: 20,
  },
  secondaryButton: {
    minHeight: 48,
    paddingHorizontal: 12,
    marginTop: 16,
    backgroundColor: theme.colors.surfaceMuted,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  photoActionRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginTop: 16,
  },
  photoActionButton: {
    flex: 1,
    minWidth: 140,
    marginTop: 0,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
    textAlign: 'center',
  },
  photoGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.sm,
    marginTop: theme.spacing.md,
  },
  photoCard: {
    width: 100,
    height: 100,
    borderRadius: theme.radius.md,
    overflow: 'hidden',
    backgroundColor: theme.colors.surfaceMuted,
    position: 'relative',
  },
  removePhotoButton: {
    position: 'absolute',
    top: 6,
    right: 6,
    zIndex: 2,
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.overlay,
    alignItems: 'center',
    justifyContent: 'center',
  },
  photo: {
    width: '100%',
    height: '100%',
  },
  photoPlaceholder: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 8,
  },
  photoPlaceholderText: {
    color: theme.colors.textMuted,
    textAlign: 'center',
    fontSize: 12,
  },
  primaryButton: {
    minHeight: 48,
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: theme.colors.textOnPrimary,
    fontWeight: '700',
    fontSize: 16,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: theme.colors.overlay,
    justifyContent: 'flex-end',
  },
  modalCard: {
    backgroundColor: theme.colors.surface,
    borderTopLeftRadius: theme.radius.lg,
    borderTopRightRadius: theme.radius.lg,
    padding: theme.spacing.md,
    maxHeight: '70%',
  },
  modalTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontWeight: '700',
    marginBottom: theme.spacing.md,
  },
  modalItem: {
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  modalItemTitle: {
    color: theme.colors.text,
    fontWeight: '700',
  },
  modalItemText: {
    color: theme.colors.textMuted,
    marginTop: 4,
  },
  cameraScreen: {
    flex: 1,
    backgroundColor: theme.colors.cameraBackdrop,
  },
  camera: {
    flex: 1,
  },
  cameraBar: {
    padding: theme.spacing.md,
    backgroundColor: theme.colors.cameraBackdrop,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: theme.spacing.md,
  },
  cameraButton: {
    flex: 1,
    minWidth: 120,
    minHeight: 48,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.surfaceElevated,
    alignItems: 'center',
    paddingVertical: 14,
  },
  cameraButtonPrimary: {
    flex: 1,
    minWidth: 120,
    minHeight: 48,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.accent,
    alignItems: 'center',
    paddingVertical: 14,
  },
  cameraButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
  },
  cameraButtonPrimaryText: {
    color: theme.colors.textOnPrimary,
    fontWeight: '700',
  },
});
