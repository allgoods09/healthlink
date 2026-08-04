import { DateTimePickerAndroid } from '@react-native-community/datetimepicker';
import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { KeyboardShiftView } from '../components/KeyboardShiftView';
import { useAppContext, useAppTheme, useThemedStyles } from '../context/AppContext';
import { useKeyboardAwareScroll } from '../hooks/useKeyboardAwareScroll';
import { i18n } from '../i18n';
import {
  calculateAgeOnDate,
  calculateAgeFromBirthDate,
  formatFriendlyDate,
  formatPurokLabel,
  formatResidentFormalName,
} from '../lib/format';
import {
  getLatestRiskAssessmentForResident,
  getResidentByLocalId,
  getRiskAssessmentByLocalId,
  saveRiskAssessment,
} from '../lib/storage';
import { AppTheme } from '../theme';
import { ResidentRecord, RiskAssessmentFlags } from '../types';

type WizardStep =
  | 'overview'
  | 'redFlags'
  | 'pastMedical'
  | 'familyLifestyle'
  | 'screening'
  | 'management';

type ChoiceOption = {
  label: string;
  value: string;
};

const STEP_LABELS: Array<{ key: WizardStep; label: string }> = [
  { key: 'overview', label: 'Overview' },
  { key: 'redFlags', label: 'Red Flags' },
  { key: 'pastMedical', label: 'Medical History' },
  { key: 'familyLifestyle', label: 'Family & Lifestyle' },
  { key: 'screening', label: 'Screening' },
  { key: 'management', label: 'Management' },
];

const RED_FLAG_FIELDS: Array<{ key: string; label: string; critical?: boolean }> = [
  { key: 'chest_pain', label: 'Chest pain', critical: true },
  { key: 'difficulty_breathing', label: 'Difficulty breathing', critical: true },
  { key: 'loss_of_consciousness', label: 'Loss of consciousness' },
  { key: 'slurred_speech', label: 'Slurred speech', critical: true },
  { key: 'facial_asymmetry', label: 'Facial asymmetry', critical: true },
  { key: 'weakness_or_numbness', label: 'Weakness or numbness on one side of the body' },
  { key: 'disoriented', label: 'Disoriented as to time, place, and person' },
  { key: 'chest_retractions', label: 'Chest retractions' },
  { key: 'seizure_or_convulsion', label: 'Seizure or convulsion' },
  { key: 'self_harm_or_suicide', label: 'Act of self-harm or suicide' },
  { key: 'agitated_or_aggressive_behavior', label: 'Agitated and/or aggressive behavior' },
  { key: 'eye_injury_or_foreign_body', label: 'Eye injury or foreign body on the eye' },
  { key: 'severe_injuries', label: 'Severe injuries' },
];

const PAST_MEDICAL_FIELDS: Array<{ key: string; label: string }> = [
  { key: 'hypertension', label: 'Hypertension' },
  { key: 'heart_diseases', label: 'Heart diseases' },
  { key: 'diabetes', label: 'Diabetes' },
  { key: 'cancer', label: 'Cancer' },
  { key: 'copd', label: 'COPD' },
  { key: 'asthma', label: 'Asthma' },
  { key: 'allergies', label: 'Allergies' },
  { key: 'mental_neurological_substance_abuse_disorders', label: 'Mental, neurological, and substance-abuse disorders' },
  { key: 'vision_problems', label: 'Vision problems' },
  { key: 'previous_surgical_history', label: 'Previous surgical history' },
  { key: 'thyroid_disorders', label: 'Thyroid disorders' },
  { key: 'kidney_disorders', label: 'Kidney disorders' },
];

const FAMILY_HISTORY_FIELDS: Array<{ key: string; label: string }> = [
  { key: 'hypertension', label: 'Hypertension' },
  { key: 'stroke', label: 'Stroke' },
  { key: 'heart_disease', label: 'Heart disease (changed from cardiovascular)' },
  { key: 'diabetes_mellitus', label: 'Diabetes mellitus' },
  { key: 'asthma', label: 'Asthma' },
  { key: 'cancer', label: 'Cancer' },
  { key: 'kidney_disease', label: 'Kidney disease' },
  { key: 'premature_coronary_disease', label: 'First-degree relative with premature coronary or vascular disease' },
  { key: 'family_tb_last_5_years', label: 'Family members having TB in the last 5 years' },
  { key: 'mental_neurological_substance_abuse_disorders', label: 'Mental, neurological, and substance-abuse disorders' },
  { key: 'copd', label: 'COPD' },
];

const DM_SYMPTOM_FIELDS: Array<{ key: string; label: string }> = [
  { key: 'polyphagia', label: 'Polyphagia' },
  { key: 'polydipsia', label: 'Polydipsia' },
  { key: 'polyuria', label: 'Polyuria' },
];

const CHRONIC_RESPIRATORY_FIELDS: Array<{ key: string; label: string }> = [
  { key: 'breathlessness', label: 'Breathlessness (or need for air)' },
  { key: 'sputum_production', label: 'Sputum (mucous) production' },
  { key: 'chronic_cough', label: 'Chronic cough' },
  { key: 'chest_tightness', label: 'Chest tightness' },
  { key: 'wheezing', label: 'Wheezing' },
];

const EMPLOYMENT_OPTIONS: ChoiceOption[] = [
  { label: 'Employed', value: 'employed' },
  { label: 'Unemployed', value: 'unemployed' },
  { label: 'Self-employed', value: 'self_employed' },
];

const IP_OPTIONS: ChoiceOption[] = [
  { label: 'IP', value: 'ip' },
  { label: 'Non-IP', value: 'non_ip' },
];

const TOBACCO_OPTIONS: ChoiceOption[] = [
  { label: 'Q1: Never used', value: 'never_used' },
  { label: 'Q2: Exposure to second hand smoke', value: 'second_hand_smoke' },
  { label: 'Q3: Former tobacco user (stopped smoking > 1 year)', value: 'former_user' },
  { label: 'Q4: Current tobacco user (currently smoking or stopped smoking < 1 year)', value: 'current_user' },
];

const ALCOHOL_OPTIONS: ChoiceOption[] = [
  { label: 'Q1: Never consumed', value: 'never_consumed' },
  { label: 'Yes, drinks alcohol', value: 'drinks_alcohol' },
];

export function RiskAssessmentFormScreen({ route, navigation }: any) {
  const { user, assignment, bumpDataVersion, requestConfirmation } = useAppContext();
  const appTheme = useAppTheme();
  const styles = useThemedStyles(createStyles);
  const { handleInputFocus, handleScroll, keyboardInset, scrollRef } =
    useKeyboardAwareScroll();
  const [resident, setResident] = useState<ResidentRecord | null>(null);
  const [loaded, setLoaded] = useState(false);
  const [step, setStep] = useState<WizardStep>('overview');
  const [localId, setLocalId] = useState<number | null>(null);
  const [serverId, setServerId] = useState<number | null>(null);
  const [mobileUuid, setMobileUuid] = useState<string | null>(null);
  const [recentAssessmentDate, setRecentAssessmentDate] = useState<string | null>(null);

  const [assessmentDate, setAssessmentDate] = useState(() => new Date());
  const [religion, setReligion] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [philhealthNumber, setPhilhealthNumber] = useState('');
  const [civilStatus, setCivilStatus] = useState('');
  const [ethnicity, setEthnicity] = useState('');
  const [pwdIdNumber, setPwdIdNumber] = useState('');
  const [weightKg, setWeightKg] = useState('');
  const [heightCm, setHeightCm] = useState('');
  const [waistCircumferenceCm, setWaistCircumferenceCm] = useState('');
  const [systolicBp, setSystolicBp] = useState('');
  const [diastolicBp, setDiastolicBp] = useState('');
  const [employmentStatus, setEmploymentStatus] = useState('');
  const [ipClassification, setIpClassification] = useState<'ip' | 'non_ip' | ''>('');
  const [redFlags, setRedFlags] = useState<RiskAssessmentFlags>({});
  const [pastMedicalHistory, setPastMedicalHistory] = useState<RiskAssessmentFlags>({});
  const [familyHistory, setFamilyHistory] = useState<RiskAssessmentFlags>({});
  const [tobaccoUse, setTobaccoUse] = useState('');
  const [alcoholConsumptionStatus, setAlcoholConsumptionStatus] = useState('');
  const [alcoholBingeFlag, setAlcoholBingeFlag] = useState<boolean | null>(null);
  const [physicalActivityMet, setPhysicalActivityMet] = useState<boolean | null>(null);
  const [highRiskDietWeekly, setHighRiskDietWeekly] = useState<boolean | null>(null);
  const [bloodSugarNotes, setBloodSugarNotes] = useState('NA');
  const [fbsResult, setFbsResult] = useState('');
  const [rbsResult, setRbsResult] = useState('');
  const [dmSymptoms, setDmSymptoms] = useState<RiskAssessmentFlags>({});
  const [lipidProfileDate, setLipidProfileDate] = useState<Date | null>(null);
  const [totalCholesterol, setTotalCholesterol] = useState('');
  const [hdl, setHdl] = useState('');
  const [ldl, setLdl] = useState('');
  const [vldl, setVldl] = useState('');
  const [triglycerides, setTriglycerides] = useState('');
  const [urinalysisProtein, setUrinalysisProtein] = useState('');
  const [urinalysisKetones, setUrinalysisKetones] = useState('');
  const [urinalysisDate, setUrinalysisDate] = useState<Date | null>(null);
  const [chronicRespiratorySymptoms, setChronicRespiratorySymptoms] = useState<RiskAssessmentFlags>({});
  const [lifestyleModification, setLifestyleModification] = useState<boolean | null>(null);
  const [antiHypertensiveMedications, setAntiHypertensiveMedications] = useState('');
  const [oralHypoglycemicMedications, setOralHypoglycemicMedications] = useState('');
  const [followUpDate, setFollowUpDate] = useState<Date | null>(null);
  const [remarks, setRemarks] = useState('');

  useEffect(() => {
    async function loadForm() {
      const nextResident = await getResidentByLocalId(route.params?.residentLocalId);

      if (!nextResident) {
        setLoaded(true);
        return;
      }

      setResident(nextResident);
      setReligion(nextResident.religion ?? '');
      setContactNumber(nextResident.contact_number ?? '');
      setCivilStatus(nextResident.civil_status ?? '');

      if (route.params?.riskAssessmentLocalId) {
        const existingAssessment = await getRiskAssessmentByLocalId(
          route.params.riskAssessmentLocalId
        );

        if (existingAssessment) {
          setLocalId(existingAssessment.local_id ?? null);
          setServerId(existingAssessment.server_id ?? null);
          setMobileUuid(existingAssessment.mobile_uuid ?? null);
          setAssessmentDate(new Date(existingAssessment.assessment_date));
          setReligion(existingAssessment.religion ?? nextResident.religion ?? '');
          setContactNumber(existingAssessment.contact_number ?? nextResident.contact_number ?? '');
          setPhilhealthNumber(existingAssessment.philhealth_number ?? '');
          setCivilStatus(existingAssessment.civil_status ?? nextResident.civil_status ?? '');
          setEthnicity(existingAssessment.ethnicity ?? '');
          setPwdIdNumber(existingAssessment.pwd_id_number ?? '');
          setWeightKg(existingAssessment.weight_kg ? String(existingAssessment.weight_kg) : '');
          setHeightCm(existingAssessment.height_cm ? String(existingAssessment.height_cm) : '');
          setWaistCircumferenceCm(
            existingAssessment.waist_circumference_cm
              ? String(existingAssessment.waist_circumference_cm)
              : ''
          );
          setSystolicBp(existingAssessment.systolic_bp ? String(existingAssessment.systolic_bp) : '');
          setDiastolicBp(existingAssessment.diastolic_bp ? String(existingAssessment.diastolic_bp) : '');
          setEmploymentStatus(existingAssessment.employment_status ?? '');
          setIpClassification((existingAssessment.ip_classification ?? '') as 'ip' | 'non_ip' | '');
          setRedFlags(existingAssessment.red_flags ?? {});
          setPastMedicalHistory(existingAssessment.past_medical_history ?? {});
          setFamilyHistory(existingAssessment.family_history ?? {});
          setTobaccoUse(existingAssessment.tobacco_use ?? '');
          setAlcoholConsumptionStatus(existingAssessment.alcohol_consumption_status ?? '');
          setAlcoholBingeFlag(existingAssessment.alcohol_binge_flag ?? null);
          setPhysicalActivityMet(existingAssessment.physical_activity_met ?? null);
          setHighRiskDietWeekly(existingAssessment.high_risk_diet_weekly ?? null);
          setBloodSugarNotes(existingAssessment.blood_sugar_notes ?? 'NA');
          setFbsResult(existingAssessment.fbs_result ?? '');
          setRbsResult(existingAssessment.rbs_result ?? '');
          setDmSymptoms(existingAssessment.dm_symptoms ?? {});
          setLipidProfileDate(existingAssessment.lipid_profile_date ? new Date(existingAssessment.lipid_profile_date) : null);
          setTotalCholesterol(existingAssessment.total_cholesterol ?? '');
          setHdl(existingAssessment.hdl ?? '');
          setLdl(existingAssessment.ldl ?? '');
          setVldl(existingAssessment.vldl ?? '');
          setTriglycerides(existingAssessment.triglycerides ?? '');
          setUrinalysisProtein(existingAssessment.urinalysis_protein ?? '');
          setUrinalysisKetones(existingAssessment.urinalysis_ketones ?? '');
          setUrinalysisDate(existingAssessment.urinalysis_date ? new Date(existingAssessment.urinalysis_date) : null);
          setChronicRespiratorySymptoms(existingAssessment.chronic_respiratory_symptoms ?? {});
          setLifestyleModification(existingAssessment.lifestyle_modification ?? null);
          setAntiHypertensiveMedications(existingAssessment.anti_hypertensive_medications ?? '');
          setOralHypoglycemicMedications(existingAssessment.oral_hypoglycemic_medications ?? '');
          setFollowUpDate(existingAssessment.follow_up_date ? new Date(existingAssessment.follow_up_date) : null);
          setRemarks(existingAssessment.remarks ?? '');
        }
      }

      if (nextResident.server_id) {
        const latestAssessment = await getLatestRiskAssessmentForResident(nextResident.server_id);
        setRecentAssessmentDate(latestAssessment?.assessment_date ?? null);
      }

      setLoaded(true);
    }

    void loadForm();
  }, [route.params?.residentLocalId, route.params?.riskAssessmentLocalId]);

  const residentAge = useMemo(
    () => calculateAgeFromBirthDate(resident?.birth_date),
    [resident?.birth_date]
  );
  const assessmentAge = useMemo(
    () => calculateAgeOnDate(resident?.birth_date, assessmentDate),
    [assessmentDate, resident?.birth_date]
  );
  const bmi = useMemo(() => {
    const weight = Number(weightKg);
    const height = Number(heightCm);

    if (!Number.isFinite(weight) || !Number.isFinite(height) || weight <= 0 || height <= 0) {
      return null;
    }

    const heightMeters = height / 100;

    if (heightMeters <= 0) {
      return null;
    }

    return (weight / (heightMeters * heightMeters)).toFixed(2);
  }, [heightCm, weightKg]);
  const hasCriticalRedFlag = Boolean(
    redFlags.chest_pain ||
      redFlags.difficulty_breathing ||
      redFlags.slurred_speech ||
      redFlags.facial_asymmetry
  );

  function updateFlag(
    current: RiskAssessmentFlags,
    setter: React.Dispatch<React.SetStateAction<RiskAssessmentFlags>>,
    key: string,
    nextValue: boolean,
    critical = false
  ) {
    setter({
      ...current,
      [key]: nextValue,
    });

    if (critical && nextValue) {
      Alert.alert(
        'CRITICAL',
        'REFER IMMEDIATELY TO PHYSICIAN / MHO.'
      );
    }
  }

  function openDatePicker(
    currentValue: Date,
    onSelect: (selectedDate: Date) => void
  ) {
    DateTimePickerAndroid.open({
      value: currentValue,
      mode: 'date',
      onChange: (event, selectedDate) => {
        if (event.type !== 'set' || !selectedDate) {
          return;
        }

        onSelect(selectedDate);
      },
    });
  }

  function formatOptionalDate(value: Date | null) {
    return value ? formatFriendlyDate(value.toISOString()) ?? value.toISOString().slice(0, 10) : 'Set date';
  }

  async function handleSave() {
    if (!resident?.server_id) {
      return;
    }

    const confirmed = await requestConfirmation({
      title: i18n.t('saveRiskAssessmentConfirmationTitle'),
      message: i18n.t('saveRiskAssessmentConfirmationBody'),
      confirmLabel: i18n.t('save'),
      tone: 'warning',
    });

    if (!confirmed) {
      return;
    }

    await saveRiskAssessment({
      local_id: localId ?? undefined,
      server_id: serverId,
      mobile_uuid: mobileUuid,
      resident_server_id: resident.server_id,
      recorded_by_user_id: user?.id ?? null,
      recorded_by_name: user?.name ?? null,
      assessment_date: assessmentDate.toISOString().slice(0, 10),
      age_years: assessmentAge,
      religion,
      contact_number: contactNumber,
      philhealth_number: philhealthNumber,
      civil_status: civilStatus,
      ethnicity,
      pwd_id_number: pwdIdNumber,
      weight_kg: weightKg ? Number(weightKg) : null,
      height_cm: heightCm ? Number(heightCm) : null,
      body_mass_index: bmi ? Number(bmi) : null,
      waist_circumference_cm: waistCircumferenceCm ? Number(waistCircumferenceCm) : null,
      systolic_bp: systolicBp ? Number(systolicBp) : null,
      diastolic_bp: diastolicBp ? Number(diastolicBp) : null,
      employment_status: employmentStatus || null,
      ip_classification: ipClassification || null,
      identity_snapshot: {
        barangay: assignment?.barangay?.name ?? null,
        purok: formatPurokLabel(
          resident.household_purok_display_name,
          resident.household_purok_id,
          'No purok'
        ),
        bhw_name: user?.name ?? null,
        resident_name: formatResidentFormalName(resident),
      },
      red_flags: redFlags,
      past_medical_history: pastMedicalHistory,
      family_history: familyHistory,
      tobacco_use: tobaccoUse || null,
      alcohol_consumption_status: alcoholConsumptionStatus || null,
      alcohol_binge_flag: alcoholBingeFlag,
      physical_activity_met: physicalActivityMet,
      high_risk_diet_weekly: highRiskDietWeekly,
      blood_sugar_notes: bloodSugarNotes,
      fbs_result: fbsResult,
      rbs_result: rbsResult,
      dm_symptoms: dmSymptoms,
      lipid_profile_date: lipidProfileDate ? lipidProfileDate.toISOString().slice(0, 10) : null,
      total_cholesterol: totalCholesterol,
      hdl,
      ldl,
      vldl,
      triglycerides,
      urinalysis_protein: urinalysisProtein,
      urinalysis_ketones: urinalysisKetones,
      urinalysis_date: urinalysisDate ? urinalysisDate.toISOString().slice(0, 10) : null,
      chronic_respiratory_symptoms: chronicRespiratorySymptoms,
      lifestyle_modification: lifestyleModification,
      anti_hypertensive_medications: antiHypertensiveMedications,
      oral_hypoglycemic_medications: oralHypoglycemicMedications,
      follow_up_date: followUpDate ? followUpDate.toISOString().slice(0, 10) : null,
      remarks,
    });

    bumpDataVersion();
    navigation.goBack();
  }

  if (!loaded) {
    return (
      <View style={styles.centeredScreen}>
        <Text style={styles.centeredText}>{i18n.t('loading')}</Text>
      </View>
    );
  }

  if (!resident) {
    return (
      <View style={styles.centeredScreen}>
        <Text style={styles.centeredText}>Resident not found on this device.</Text>
      </View>
    );
  }

  if (!resident.server_id || assessmentAge === null || assessmentAge < 20) {
    return (
      <View style={styles.centeredScreen}>
        <View style={styles.blockedCard}>
          <Text style={styles.blockedTitle}>PhilPEN is not available</Text>
          <Text style={styles.blockedBody}>
            Risk assessment is only for verified residents aged 20 years old and above.
          </Text>
        </View>
      </View>
    );
  }

  const stepIndex = STEP_LABELS.findIndex((item) => item.key === step);
  const currentStep = STEP_LABELS[stepIndex];

  return (
    <KeyboardShiftView style={styles.screen}>
      <ScrollView
        ref={scrollRef}
        style={styles.screen}
        contentContainerStyle={[
          styles.content,
          { paddingBottom: appTheme.spacing.xl + keyboardInset },
        ]}
        keyboardShouldPersistTaps="handled"
        onScroll={handleScroll}
        scrollEventThrottle={16}
      >
        <View style={styles.hero}>
          <Text style={styles.heroEyebrow}>PhilPEN Risk Assessment</Text>
          <Text style={styles.heroTitle}>{formatResidentFormalName(resident)}</Text>
          <Text style={styles.heroSubline}>
            {resident.sex} · Age {assessmentAge ?? residentAge ?? 'N/A'} · {formatPurokLabel(resident.household_purok_display_name, resident.household_purok_id)}
          </Text>
        </View>

      {recentAssessmentDate ? (
        <View style={styles.noticeCard}>
          <Text style={styles.noticeTitle}>Recent assessment found</Text>
          <Text style={styles.noticeBody}>
            This resident was last assessed on {formatFriendlyDate(recentAssessmentDate) ?? recentAssessmentDate}. Proceeding will log a new assessment entry.
          </Text>
        </View>
      ) : null}

      {hasCriticalRedFlag ? (
        <View style={styles.criticalCard}>
          <Text style={styles.criticalTitle}>Immediate referral required</Text>
          <Text style={styles.criticalBody}>
            One or more critical red flags were marked. Refer this resident immediately to the Physician / MHO.
          </Text>
        </View>
      ) : null}

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.stepRow}>
        {STEP_LABELS.map((item, index) => (
          <Pressable
            key={item.key}
            onPress={() => setStep(item.key)}
            style={[styles.stepChip, index === stepIndex && styles.stepChipActive]}
          >
            <Text style={[styles.stepChipText, index === stepIndex && styles.stepChipTextActive]}>
              {index + 1}. {item.label}
            </Text>
          </Pressable>
        ))}
      </ScrollView>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>{currentStep.label}</Text>
        {step === 'overview' ? (
          <View style={styles.sectionStack}>
            <ReadOnlyRow label="Barangay" value={assignment?.barangay?.name ?? 'Not available'} />
            <ReadOnlyRow
              label="Purok"
              value={formatPurokLabel(
                resident.household_purok_display_name,
                resident.household_purok_id,
                'Not available'
              )}
            />
            <ReadOnlyRow label="BHW" value={user?.name ?? 'Unknown BHW'} />
            <ReadOnlyRow label="Birthdate" value={formatFriendlyDate(resident.birth_date) ?? resident.birth_date} />
            <ReadOnlyRow label="Contact Number" value={contactNumber || 'No contact saved'} />

            <FieldLabel label="Date of assessment" />
            <Pressable
              onPress={() => openDatePicker(assessmentDate, setAssessmentDate)}
              style={styles.dateButton}
            >
              <Text style={styles.dateButtonText}>
                {formatFriendlyDate(assessmentDate.toISOString()) ?? assessmentDate.toISOString().slice(0, 10)}
              </Text>
            </Pressable>

            <TextInputField label="PhilHealth Number" value={philhealthNumber} onChangeText={setPhilhealthNumber} onFocus={handleInputFocus} />
            <TextInputField label="Religion" value={religion} onChangeText={setReligion} onFocus={handleInputFocus} />
            <TextInputField label="Civil Status" value={civilStatus} onChangeText={setCivilStatus} onFocus={handleInputFocus} />
            <TextInputField label="Ethnicity" value={ethnicity} onChangeText={setEthnicity} onFocus={handleInputFocus} />
            <TextInputField label="PWD ID Number (if applicable)" value={pwdIdNumber} onChangeText={setPwdIdNumber} onFocus={handleInputFocus} />

            <View style={styles.doubleRow}>
              <NumericField label="Weight (kg)" value={weightKg} onChangeText={setWeightKg} onFocus={handleInputFocus} />
              <NumericField label="Height (cm)" value={heightCm} onChangeText={setHeightCm} onFocus={handleInputFocus} />
            </View>
            <View style={styles.doubleRow}>
              <NumericField label="Waist Circumference" value={waistCircumferenceCm} onChangeText={setWaistCircumferenceCm} onFocus={handleInputFocus} />
              <ReadOnlyRow label="BMI" value={bmi ? `${bmi}` : 'Waiting for weight and height'} compact />
            </View>
            <View style={styles.doubleRow}>
              <NumericField label="Systolic BP" value={systolicBp} onChangeText={setSystolicBp} onFocus={handleInputFocus} />
              <NumericField label="Diastolic BP" value={diastolicBp} onChangeText={setDiastolicBp} onFocus={handleInputFocus} />
            </View>

            <ChoiceGroup
              label="Employment Status"
              value={employmentStatus}
              options={EMPLOYMENT_OPTIONS}
              onChange={setEmploymentStatus}
            />
            <ChoiceGroup
              label="IP Classification"
              value={ipClassification}
              options={IP_OPTIONS}
              onChange={(value) => setIpClassification(value as 'ip' | 'non_ip')}
            />
          </View>
        ) : null}

        {step === 'redFlags' ? (
          <View style={styles.sectionStack}>
            <Text style={styles.helperText}>
              If any critical red flag is marked yes, refer immediately to the Physician / MHO.
            </Text>
            {RED_FLAG_FIELDS.map((field) => (
              <BooleanRow
                key={field.key}
                label={field.label}
                value={Boolean(redFlags[field.key])}
                highlight={field.critical}
                onChange={(nextValue) =>
                  updateFlag(redFlags, setRedFlags, field.key, nextValue, field.critical)
                }
              />
            ))}
          </View>
        ) : null}

        {step === 'pastMedical' ? (
          <View style={styles.sectionStack}>
            {PAST_MEDICAL_FIELDS.map((field) => (
              <BooleanRow
                key={field.key}
                label={field.label}
                value={Boolean(pastMedicalHistory[field.key])}
                onChange={(nextValue) =>
                  updateFlag(pastMedicalHistory, setPastMedicalHistory, field.key, nextValue)
                }
              />
            ))}
          </View>
        ) : null}

        {step === 'familyLifestyle' ? (
          <View style={styles.sectionStack}>
            <Text style={styles.subsectionTitle}>Family History</Text>
            {FAMILY_HISTORY_FIELDS.map((field) => (
              <BooleanRow
                key={field.key}
                label={field.label}
                value={Boolean(familyHistory[field.key])}
                onChange={(nextValue) =>
                  updateFlag(familyHistory, setFamilyHistory, field.key, nextValue)
                }
              />
            ))}

            <Text style={styles.subsectionTitle}>Non-Communicable Risk Factors</Text>
            <ChoiceGroup
              label="Tobacco Use"
              value={tobaccoUse}
              options={TOBACCO_OPTIONS}
              onChange={setTobaccoUse}
            />
            <ChoiceGroup
              label="Alcohol Intake"
              value={alcoholConsumptionStatus}
              options={ALCOHOL_OPTIONS}
              onChange={setAlcoholConsumptionStatus}
            />
            {alcoholConsumptionStatus === 'drinks_alcohol' ? (
              <BooleanRow
                label="5 or more drinks for men, or 4 or more for women in one sitting in the past year"
                value={Boolean(alcoholBingeFlag)}
                onChange={setAlcoholBingeFlag}
              />
            ) : null}
            <BooleanRow
              label="Does the patient do at least 2.5 hours a week of moderate-intensity physical activity?"
              value={Boolean(physicalActivityMet)}
              onChange={setPhysicalActivityMet}
            />
            <BooleanRow
              label="Does the patient eat high-fat, high-salt, fried, or high-sugar food and drinks weekly?"
              value={Boolean(highRiskDietWeekly)}
              onChange={setHighRiskDietWeekly}
            />
          </View>
        ) : null}

        {step === 'screening' ? (
          <View style={styles.sectionStack}>
            <Text style={styles.subsectionTitle}>Risk Screening</Text>
            <TextInputField
              label="Blood Sugar (write N/A if not applicable)"
              value={bloodSugarNotes}
              onChangeText={setBloodSugarNotes}
              onFocus={handleInputFocus}
            />
            <View style={styles.doubleRow}>
              <TextInputField label="FBS Result" value={fbsResult} onChangeText={setFbsResult} onFocus={handleInputFocus} />
              <TextInputField label="RBS Result" value={rbsResult} onChangeText={setRbsResult} onFocus={handleInputFocus} />
            </View>
            <Text style={styles.helperLabel}>Check if DM clinical symptoms are present</Text>
            {DM_SYMPTOM_FIELDS.map((field) => (
              <BooleanRow
                key={field.key}
                label={field.label}
                value={Boolean(dmSymptoms[field.key])}
                onChange={(nextValue) =>
                  updateFlag(dmSymptoms, setDmSymptoms, field.key, nextValue)
                }
              />
            ))}

            <FieldLabel label="Lipid Profile Date Taken" />
            <Pressable
              onPress={() => openDatePicker(lipidProfileDate ?? new Date(), setLipidProfileDate)}
              style={styles.dateButton}
            >
              <Text style={styles.dateButtonText}>{formatOptionalDate(lipidProfileDate)}</Text>
            </Pressable>
            <View style={styles.doubleRow}>
              <TextInputField label="Total Cholesterol" value={totalCholesterol} onChangeText={setTotalCholesterol} onFocus={handleInputFocus} />
              <TextInputField label="HDL" value={hdl} onChangeText={setHdl} onFocus={handleInputFocus} />
            </View>
            <View style={styles.doubleRow}>
              <TextInputField label="LDL" value={ldl} onChangeText={setLdl} onFocus={handleInputFocus} />
              <TextInputField label="VLDL" value={vldl} onChangeText={setVldl} onFocus={handleInputFocus} />
            </View>
            <TextInputField label="Triglyceride" value={triglycerides} onChangeText={setTriglycerides} onFocus={handleInputFocus} />

            <Text style={styles.subsectionTitle}>Urinalysis / Urine Dipstick Test</Text>
            <FieldLabel label="Urinalysis Date Taken" />
            <Pressable
              onPress={() => openDatePicker(urinalysisDate ?? new Date(), setUrinalysisDate)}
              style={styles.dateButton}
            >
              <Text style={styles.dateButtonText}>{formatOptionalDate(urinalysisDate)}</Text>
            </Pressable>
            <View style={styles.doubleRow}>
              <TextInputField label="Protein" value={urinalysisProtein} onChangeText={setUrinalysisProtein} onFocus={handleInputFocus} />
              <TextInputField label="Ketones" value={urinalysisKetones} onChangeText={setUrinalysisKetones} onFocus={handleInputFocus} />
            </View>

            <Text style={styles.subsectionTitle}>Chronic Respiratory Diseases (Asthma and COPD)</Text>
            {CHRONIC_RESPIRATORY_FIELDS.map((field) => (
              <BooleanRow
                key={field.key}
                label={field.label}
                value={Boolean(chronicRespiratorySymptoms[field.key])}
                onChange={(nextValue) =>
                  updateFlag(
                    chronicRespiratorySymptoms,
                    setChronicRespiratorySymptoms,
                    field.key,
                    nextValue
                  )
                }
              />
            ))}
          </View>
        ) : null}

        {step === 'management' ? (
          <View style={styles.sectionStack}>
            <BooleanRow
              label="Lifestyle Modification"
              value={Boolean(lifestyleModification)}
              onChange={setLifestyleModification}
            />
            <TextInputField
              label="A. Anti-hypertensives (existing patient medications only)"
              value={antiHypertensiveMedications}
              onChangeText={setAntiHypertensiveMedications}
              onFocus={handleInputFocus}
              multiline
            />
            <TextInputField
              label="B. Oral Hypoglycemic Agents / Insulin (existing patient medications only)"
              value={oralHypoglycemicMedications}
              onChangeText={setOralHypoglycemicMedications}
              onFocus={handleInputFocus}
              multiline
            />
            <FieldLabel label="Date of follow-up" />
            <Pressable
              onPress={() => openDatePicker(followUpDate ?? new Date(), setFollowUpDate)}
              style={styles.dateButton}
            >
              <Text style={styles.dateButtonText}>{formatOptionalDate(followUpDate)}</Text>
            </Pressable>
            <TextInputField label="Remarks" value={remarks} onChangeText={setRemarks} onFocus={handleInputFocus} multiline />

            <View style={styles.warningBox}>
              <Text style={styles.warningTitle}>Reminder</Text>
              <Text style={styles.warningBody}>
                BHWs must never prescribe medicines here. Only record existing medications the patient reports taking, or basic lifestyle advice already given.
              </Text>
            </View>
          </View>
        ) : null}
      </View>

        <View style={styles.actionRow}>
          <Pressable
            onPress={() => {
              if (stepIndex === 0) {
                navigation.goBack();
                return;
              }

              setStep(STEP_LABELS[stepIndex - 1].key);
            }}
            style={[styles.actionButton, styles.secondaryButton]}
          >
            <Text style={styles.secondaryButtonText}>
              {stepIndex === 0 ? i18n.t('cancel') : 'Back'}
            </Text>
          </Pressable>

          {stepIndex < STEP_LABELS.length - 1 ? (
            <Pressable
              onPress={() => setStep(STEP_LABELS[stepIndex + 1].key)}
              style={[styles.actionButton, styles.primaryButton]}
            >
              <Text style={styles.primaryButtonText}>Next</Text>
            </Pressable>
          ) : (
            <Pressable onPress={() => void handleSave()} style={[styles.actionButton, styles.primaryButton]}>
              <Text style={styles.primaryButtonText}>{i18n.t('save')}</Text>
            </Pressable>
          )}
        </View>
      </ScrollView>
    </KeyboardShiftView>
  );
}

function FieldLabel({ label }: { label: string }) {
  const styles = useThemedStyles(createStyles);

  return <Text style={styles.fieldLabel}>{label}</Text>;
}

function TextInputField({
  label,
  value,
  onChangeText,
  onFocus,
  multiline = false,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  onFocus?: () => void;
  multiline?: boolean;
}) {
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);

  return (
    <View>
      <FieldLabel label={label} />
      <TextInput
        value={value}
        onChangeText={onChangeText}
        onFocus={onFocus}
        multiline={multiline}
        placeholderTextColor={theme.colors.placeholder}
        style={[styles.input, multiline && styles.textArea]}
      />
    </View>
  );
}

function NumericField({
  label,
  value,
  onChangeText,
  onFocus,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  onFocus?: () => void;
}) {
  const theme = useAppTheme();
  const styles = useThemedStyles(createStyles);

  return (
    <View style={styles.flexField}>
      <FieldLabel label={label} />
      <TextInput
        value={value}
        onChangeText={onChangeText}
        onFocus={onFocus}
        keyboardType="numeric"
        placeholderTextColor={theme.colors.placeholder}
        style={styles.input}
      />
    </View>
  );
}

function ReadOnlyRow({
  label,
  value,
  compact = false,
}: {
  label: string;
  value: string;
  compact?: boolean;
}) {
  const styles = useThemedStyles(createStyles);

  return (
    <View style={compact ? styles.readOnlyCompact : styles.readOnlyBlock}>
      <Text style={styles.readOnlyLabel}>{label}</Text>
      <Text style={styles.readOnlyValue}>{value}</Text>
    </View>
  );
}

function BooleanRow({
  label,
  value,
  onChange,
  highlight = false,
}: {
  label: string;
  value: boolean;
  onChange: (nextValue: boolean) => void;
  highlight?: boolean;
}) {
  const styles = useThemedStyles(createStyles);

  return (
    <View style={[styles.booleanRow, highlight && styles.booleanRowHighlight]}>
      <Text style={styles.booleanLabel}>{label}</Text>
      <View style={styles.booleanActions}>
        <Pressable
          onPress={() => onChange(true)}
          style={[styles.booleanChip, value && styles.booleanChipYesActive]}
        >
          <Text style={[styles.booleanChipText, value && styles.booleanChipTextActive]}>Yes</Text>
        </Pressable>
        <Pressable
          onPress={() => onChange(false)}
          style={[styles.booleanChip, !value && styles.booleanChipNoActive]}
        >
          <Text style={[styles.booleanChipText, !value && styles.booleanChipTextActive]}>No</Text>
        </Pressable>
      </View>
    </View>
  );
}

function ChoiceGroup({
  label,
  value,
  options,
  onChange,
}: {
  label: string;
  value: string;
  options: ChoiceOption[];
  onChange: (value: string) => void;
}) {
  const styles = useThemedStyles(createStyles);

  return (
    <View>
      <FieldLabel label={label} />
      <View style={styles.choiceGroup}>
        {options.map((option) => (
          <Pressable
            key={option.value}
            onPress={() => onChange(option.value)}
            style={[
              styles.choiceOption,
              value === option.value && styles.choiceOptionActive,
            ]}
          >
            <Text
              style={[
                styles.choiceOptionText,
                value === option.value && styles.choiceOptionTextActive,
              ]}
            >
              {option.label}
            </Text>
          </Pressable>
        ))}
      </View>
    </View>
  );
}

const createStyles = (theme: AppTheme) => StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  content: {
    padding: theme.spacing.md,
    paddingBottom: theme.spacing.xl,
    gap: theme.spacing.md,
  },
  centeredScreen: {
    flex: 1,
    backgroundColor: theme.colors.background,
    alignItems: 'center',
    justifyContent: 'center',
    padding: theme.spacing.lg,
  },
  centeredText: {
    color: theme.colors.textMuted,
    textAlign: 'center',
  },
  hero: {
    backgroundColor: theme.colors.primary,
    borderRadius: 28,
    padding: theme.spacing.lg,
  },
  heroEyebrow: {
    color: theme.colors.heroTextMuted,
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  heroTitle: {
    color: theme.colors.textOnPrimary,
    fontSize: 27,
    fontWeight: '700',
    marginTop: 10,
  },
  heroSubline: {
    color: theme.colors.heroTextMuted,
    marginTop: 8,
    lineHeight: 21,
  },
  noticeCard: {
    backgroundColor: theme.colors.warningSoft,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.warningBorder,
    padding: theme.spacing.md,
  },
  noticeTitle: {
    color: theme.colors.warning,
    fontWeight: '700',
    fontSize: 15,
  },
  noticeBody: {
    color: theme.colors.warning,
    marginTop: 8,
    lineHeight: 21,
  },
  criticalCard: {
    backgroundColor: theme.colors.dangerSoft,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.dangerBorder,
    padding: theme.spacing.md,
  },
  criticalTitle: {
    color: theme.colors.danger,
    fontWeight: '700',
    fontSize: 15,
  },
  criticalBody: {
    color: theme.colors.danger,
    marginTop: 8,
    lineHeight: 21,
  },
  stepRow: {
    gap: theme.spacing.sm,
    paddingRight: theme.spacing.md,
  },
  stepChip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  stepChipActive: {
    backgroundColor: theme.colors.primary,
    borderColor: theme.colors.primary,
  },
  stepChipText: {
    color: theme.colors.text,
    fontSize: 13,
    fontWeight: '600',
  },
  stepChipTextActive: {
    color: theme.colors.textOnPrimary,
  },
  card: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.md,
  },
  sectionTitle: {
    color: theme.colors.text,
    fontSize: 20,
    fontWeight: '700',
    marginBottom: 14,
  },
  subsectionTitle: {
    color: theme.colors.text,
    fontSize: 16,
    fontWeight: '700',
    marginTop: 8,
  },
  sectionStack: {
    gap: theme.spacing.md,
  },
  helperText: {
    color: theme.colors.textMuted,
    lineHeight: 21,
  },
  helperLabel: {
    color: theme.colors.textMuted,
    fontWeight: '600',
    fontSize: 13,
  },
  fieldLabel: {
    color: theme.colors.textMuted,
    fontSize: 13,
    fontWeight: '600',
    marginBottom: 8,
  },
  input: {
    backgroundColor: theme.colors.background,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 14,
    color: theme.colors.text,
  },
  textArea: {
    minHeight: 96,
    textAlignVertical: 'top',
  },
  doubleRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  flexField: {
    flex: 1,
  },
  readOnlyBlock: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    padding: theme.spacing.md,
  },
  readOnlyCompact: {
    flex: 1,
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    padding: theme.spacing.md,
  },
  readOnlyLabel: {
    color: theme.colors.textMuted,
    fontSize: 12,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
  },
  readOnlyValue: {
    color: theme.colors.text,
    fontSize: 15,
    fontWeight: '700',
    marginTop: 6,
    lineHeight: 21,
  },
  dateButton: {
    backgroundColor: theme.colors.background,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  dateButtonText: {
    color: theme.colors.text,
    fontWeight: '600',
  },
  booleanRow: {
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    padding: theme.spacing.md,
    gap: theme.spacing.sm,
  },
  booleanRowHighlight: {
    borderColor: theme.colors.dangerBorder,
    backgroundColor: theme.colors.dangerSoft,
  },
  booleanLabel: {
    color: theme.colors.text,
    fontSize: 15,
    fontWeight: '600',
    lineHeight: 22,
  },
  booleanActions: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  booleanChip: {
    flex: 1,
    alignItems: 'center',
    borderRadius: 999,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingVertical: 10,
    backgroundColor: theme.colors.surface,
  },
  booleanChipYesActive: {
    backgroundColor: theme.colors.success,
    borderColor: theme.colors.success,
  },
  booleanChipNoActive: {
    backgroundColor: theme.colors.primaryDark,
    borderColor: theme.colors.primaryDark,
  },
  booleanChipText: {
    color: theme.colors.text,
    fontWeight: '700',
  },
  booleanChipTextActive: {
    color: theme.colors.textOnPrimary,
  },
  choiceGroup: {
    gap: theme.spacing.sm,
  },
  choiceOption: {
    borderRadius: theme.radius.md,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: 14,
    paddingVertical: 12,
    backgroundColor: theme.colors.surface,
  },
  choiceOptionActive: {
    backgroundColor: theme.colors.primarySoft,
    borderColor: theme.colors.primary,
  },
  choiceOptionText: {
    color: theme.colors.text,
    fontWeight: '600',
    lineHeight: 20,
  },
  choiceOptionTextActive: {
    color: theme.colors.primaryDark,
  },
  warningBox: {
    backgroundColor: theme.colors.primarySoft,
    borderRadius: theme.radius.md,
    padding: theme.spacing.md,
  },
  warningTitle: {
    color: theme.colors.primaryDark,
    fontWeight: '700',
    fontSize: 15,
  },
  warningBody: {
    color: theme.colors.text,
    marginTop: 8,
    lineHeight: 21,
  },
  blockedCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    padding: theme.spacing.lg,
    maxWidth: 420,
  },
  blockedTitle: {
    color: theme.colors.text,
    fontSize: 18,
    fontWeight: '700',
  },
  blockedBody: {
    color: theme.colors.textMuted,
    marginTop: 8,
    lineHeight: 22,
  },
  actionRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  actionButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: theme.radius.md,
    paddingVertical: 14,
  },
  primaryButton: {
    backgroundColor: theme.colors.primary,
  },
  secondaryButton: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  primaryButtonText: {
    color: theme.colors.textOnPrimary,
    fontWeight: '700',
    fontSize: 16,
  },
  secondaryButtonText: {
    color: theme.colors.text,
    fontWeight: '700',
    fontSize: 16,
  },
});
