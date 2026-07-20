import { Ionicons } from '@expo/vector-icons';
import { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import { CameraView, useCameraPermissions } from 'expo-camera';
import * as ImagePicker from 'expo-image-picker';
import React, { useEffect, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { useAppContext } from '../context/AppContext';
import { i18n } from '../i18n';
import {
  formatFriendlyDate,
  formatFriendlyTime,
} from '../lib/format';
import {
  getHouseholdByLocalId,
  getHouseholds,
  getVisitByLocalId,
  saveVisit,
} from '../lib/storage';
import { theme } from '../theme';
import { HouseholdRecord, VisitPhoto } from '../types';

export function VisitFormScreen({ route, navigation }: any) {
  const cameraRef = useRef<CameraView | null>(null);
  const { assignment, bumpDataVersion } = useAppContext();
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
  const assignedPurokId = assignment?.purok?.id ?? null;

  useEffect(() => {
    async function loadWritableHouseholds() {
      const records = await getHouseholds();
      setHouseholds(
        assignedPurokId === null
          ? records
          : records.filter((household) => household.purok_id === assignedPurokId)
      );
    }

    void loadWritableHouseholds();
  }, [assignedPurokId]);

  useEffect(() => {
    async function loadExisting() {
      if (route.params?.householdLocalId) {
        const preselected = await getHouseholdByLocalId(route.params.householdLocalId);
        if (preselected) {
          setSelectedHousehold(preselected);
        }
      }

      if (!route.params?.localId) return;

      const existing = await getVisitByLocalId(route.params.localId);

      if (!existing) return;

      setLocalId(existing.local_id ?? null);
      setServerId(existing.server_id ?? null);
      setMobileUuid(existing.mobile_uuid ?? null);
      const existingVisitedAt = new Date(existing.visited_at);
      setVisitedAt(Number.isNaN(existingVisitedAt.getTime()) ? new Date() : existingVisitedAt);
      setNotes(existing.notes ?? '');
      setPhotos(existing.photos ?? []);

      if (existing.household_server_id || existing.household_mobile_uuid) {
        const existingHousehold = (await getHouseholds()).find(
          (household) =>
            household.server_id === existing.household_server_id ||
            household.mobile_uuid === existing.household_mobile_uuid
        );

        if (existingHousehold) {
          setSelectedHousehold(existingHousehold);
        }
      }
    }

    void loadExisting();
  }, [route.params?.householdLocalId, route.params?.localId]);

  function appendPhoto(photo: VisitPhoto) {
    setPhotos((current) => [...current, photo]);
  }

  function openVisitDatePicker() {
    DateTimePickerAndroid.open({
      value: visitedAt,
      mode: 'date',
      onChange: (event, selectedDate) => {
        if (event.type !== 'set' || !selectedDate) {
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
    DateTimePickerAndroid.open({
      value: visitedAt,
      mode: 'time',
      is24Hour: false,
      onChange: (event, selectedDate) => {
        if (event.type !== 'set' || !selectedDate) {
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
    if (!mediaPermission?.granted) {
      const result = await requestMediaPermission();

      if (!result.granted) {
        Alert.alert(i18n.t('uploadFromGallery'), i18n.t('galleryPermissionBody'));
        return;
      }
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsMultipleSelection: false,
      quality: 0.6,
      base64: true,
    });

    if (result.canceled || result.assets.length === 0) {
      return;
    }

    const asset = result.assets[0];

    if (!asset.base64) {
      return;
    }

    appendPhoto({
      uri: asset.uri,
      base64: asset.base64,
      file_name: asset.fileName ?? `visit-gallery-${Date.now()}.jpg`,
      mime_type: asset.mimeType ?? 'image/jpeg',
      file_size_bytes: asset.fileSize ?? null,
      captured_at: new Date().toISOString(),
    });
  }

  async function handleTakePhoto() {
    if (!permission?.granted) {
      const result = await requestPermission();
      if (!result.granted) {
        return;
      }
    }

    setCameraVisible(true);
  }

  async function capturePhoto() {
    const photo = await cameraRef.current?.takePictureAsync({
      base64: true,
      quality: 0.5,
    });

    if (!photo?.base64) {
      return;
    }

    appendPhoto({
      uri: photo.uri,
      base64: photo.base64,
      file_name: `visit-${Date.now()}.jpg`,
      mime_type: 'image/jpeg',
      captured_at: new Date().toISOString(),
    });
    setCameraVisible(false);
  }

  function removePhoto(index: number) {
    setPhotos((current) => current.filter((_, photoIndex) => photoIndex !== index));
  }

  async function handleSave() {
    if (!selectedHousehold) return;

    await saveVisit({
      local_id: localId ?? undefined,
      server_id: serverId,
      mobile_uuid: mobileUuid,
      household_server_id: selectedHousehold.server_id ?? null,
      household_mobile_uuid: selectedHousehold.mobile_uuid ?? null,
      visited_at: visitedAt.toISOString(),
      notes,
      photos,
    });
    bumpDataVersion();
    navigation.goBack();
  }

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <Text style={styles.label}>{i18n.t('chooseHousehold')}</Text>
        <Pressable onPress={() => setChooserVisible(true)} style={styles.pickerButton}>
          <Text style={styles.pickerLabel}>
            {selectedHousehold?.household_no ?? i18n.t('chooseHousehold')}
          </Text>
        </Pressable>

        <Text style={styles.label}>{i18n.t('visitedAt')}</Text>
        <View style={styles.dateRow}>
          <View style={styles.dateFieldBlock}>
            <Text style={styles.secondaryLabel}>{i18n.t('visitDate')}</Text>
            <Pressable onPress={openVisitDatePicker} style={[styles.input, styles.dateSelector]}>
              <Text style={styles.dateSelectorText}>
                {formatFriendlyDate(visitedAt.toISOString()) ?? i18n.t('birthDatePlaceholder')}
              </Text>
            </Pressable>
          </View>
          <Pressable onPress={openVisitDatePicker} style={styles.calendarButton}>
            <Text style={styles.calendarButtonText}>{i18n.t('openCalendar')}</Text>
          </Pressable>
        </View>

        <View style={styles.dateRow}>
          <View style={styles.dateFieldBlock}>
            <Text style={styles.secondaryLabel}>{i18n.t('visitTime')}</Text>
            <Pressable onPress={openVisitTimePicker} style={[styles.input, styles.dateSelector]}>
              <Text style={styles.dateSelectorText}>
                {formatFriendlyTime(visitedAt.toISOString()) ?? '--:--'}
              </Text>
            </Pressable>
          </View>
          <Pressable onPress={openVisitTimePicker} style={styles.calendarButton}>
            <Text style={styles.calendarButtonText}>{i18n.t('openClock')}</Text>
          </Pressable>
        </View>

        <Text style={styles.label}>{i18n.t('notes')}</Text>
        <TextInput
          value={notes}
          onChangeText={setNotes}
          style={[styles.input, styles.multiline]}
          multiline
        />

        <Text style={styles.photoNote}>{i18n.t('photoIntegrityNote')}</Text>

        <View style={styles.photoActionRow}>
          <Pressable onPress={handleTakePhoto} style={[styles.secondaryButton, styles.photoActionButton]}>
            <Text style={styles.secondaryButtonText}>
              {photos.length > 0 ? i18n.t('addAnotherPhoto') : i18n.t('takePhoto')}
            </Text>
          </Pressable>
          <Pressable onPress={handlePickFromGallery} style={[styles.secondaryButton, styles.photoActionButton]}>
            <Text style={styles.secondaryButtonText}>{i18n.t('uploadFromGallery')}</Text>
          </Pressable>
        </View>

        <View style={styles.photoGrid}>
          {photos.map((photo, index) => (
            <View key={`${photo.file_name}-${index}`} style={styles.photoCard}>
              <Pressable
                onPress={() => removePhoto(index)}
                style={styles.removePhotoButton}
                hitSlop={10}
              >
                <Ionicons name="close" size={14} color="#fff" />
              </Pressable>
              {photo.uri ? (
                <Image source={{ uri: photo.uri }} style={styles.photo} />
              ) : (
                <View style={styles.photoPlaceholder}>
                  <Text style={styles.photoPlaceholderText}>{i18n.t('syncedPhotoLabel')}</Text>
                </View>
              )}
            </View>
          ))}
        </View>
      </View>

      <Pressable onPress={handleSave} style={styles.primaryButton}>
        <Text style={styles.primaryButtonText}>{i18n.t('save')}</Text>
      </Pressable>

      <Modal visible={chooserVisible} transparent animationType="slide">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <FlatList
              data={households}
              keyExtractor={(item) => String(item.local_id ?? item.server_id ?? item.mobile_uuid)}
              ListHeaderComponent={<Text style={styles.modalTitle}>{i18n.t('chooseHousehold')}</Text>}
              renderItem={({ item }) => (
                <Pressable
                  onPress={() => {
                    setSelectedHousehold(item);
                    setChooserVisible(false);
                  }}
                  style={styles.modalItem}
                >
                  <Text style={styles.modalItemTitle}>{item.household_no}</Text>
                  <Text style={styles.modalItemText}>{item.household_address}</Text>
                </Pressable>
              )}
            />
            <Pressable onPress={() => setChooserVisible(false)} style={styles.secondaryButton}>
              <Text style={styles.secondaryButtonText}>{i18n.t('cancel')}</Text>
            </Pressable>
          </View>
        </View>
      </Modal>

      <Modal visible={cameraVisible} animationType="slide">
        <View style={styles.cameraScreen}>
          <CameraView ref={cameraRef} style={styles.camera} facing="back" />
          <View style={styles.cameraBar}>
            <Pressable onPress={() => setCameraVisible(false)} style={styles.cameraButton}>
              <Text style={styles.cameraButtonText}>{i18n.t('cancel')}</Text>
            </Pressable>
            <Pressable onPress={capturePhoto} style={styles.cameraButtonPrimary}>
              <Text style={styles.cameraButtonPrimaryText}>{i18n.t('takePhoto')}</Text>
            </Pressable>
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
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
    backgroundColor: '#FAFBFA',
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: theme.colors.text,
  },
  dateRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: theme.spacing.sm,
    marginTop: 10,
  },
  dateFieldBlock: {
    flex: 1,
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
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: '#FAFBFA',
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
    marginTop: 16,
    backgroundColor: theme.colors.surfaceMuted,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  photoActionRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
    marginTop: 16,
  },
  photoActionButton: {
    flex: 1,
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
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: 'rgba(15, 23, 42, 0.82)',
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
    backgroundColor: theme.colors.primary,
    borderRadius: theme.radius.md,
    alignItems: 'center',
    paddingVertical: 14,
  },
  primaryButtonText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 16,
  },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(16, 24, 40, 0.28)',
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
    backgroundColor: '#000',
  },
  camera: {
    flex: 1,
  },
  cameraBar: {
    padding: theme.spacing.md,
    backgroundColor: 'rgba(0,0,0,0.7)',
    flexDirection: 'row',
    gap: theme.spacing.md,
  },
  cameraButton: {
    flex: 1,
    borderRadius: theme.radius.md,
    backgroundColor: '#1f2937',
    alignItems: 'center',
    paddingVertical: 14,
  },
  cameraButtonPrimary: {
    flex: 1,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.accent,
    alignItems: 'center',
    paddingVertical: 14,
  },
  cameraButtonText: {
    color: '#fff',
    fontWeight: '700',
  },
  cameraButtonPrimaryText: {
    color: '#fff',
    fontWeight: '700',
  },
});
