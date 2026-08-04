const DATE_INPUT_PLACEHOLDER = 'YYYY/MM/DD';

type ResidentNameShape = {
  first_name?: string | null;
  middle_name?: string | null;
  last_name?: string | null;
  suffix?: string | null;
};

function pad(value: number) {
  return String(value).padStart(2, '0');
}

function parseDateOnly(value: string) {
  const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (!match) {
    return null;
  }

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const date = new Date(year, month - 1, day);

  if (
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null;
  }

  return date;
}

function parseDateValue(value: string) {
  return parseDateOnly(value) ?? (() => {
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  })();
}

function startOfLocalDay(value: Date) {
  return new Date(value.getFullYear(), value.getMonth(), value.getDate());
}

function normalizeNamePart(value: string | null | undefined) {
  const normalized = (value ?? '').trim().replace(/\s+/g, ' ');

  return normalized !== '' ? normalized : null;
}

export function formatResidentDisplayName(
  resident: ResidentNameShape | null | undefined,
  fallback = 'Unknown resident'
) {
  if (!resident) {
    return fallback;
  }

  const parts = [
    normalizeNamePart(resident.first_name),
    normalizeNamePart(resident.middle_name),
    normalizeNamePart(resident.last_name),
  ].filter(Boolean);

  const suffix = normalizeNamePart(resident.suffix);
  const baseName = parts.join(' ').trim();

  if (!baseName) {
    return fallback;
  }

  return suffix ? `${baseName} ${suffix}` : baseName;
}

export function formatResidentFormalName(
  resident: ResidentNameShape | null | undefined,
  fallback = 'Unknown resident'
) {
  if (!resident) {
    return fallback;
  }

  const firstName = normalizeNamePart(resident.first_name);
  const middleName = normalizeNamePart(resident.middle_name);
  const lastName = normalizeNamePart(resident.last_name);
  const suffix = normalizeNamePart(resident.suffix);

  if (!firstName && !lastName) {
    return fallback;
  }

  if (!lastName) {
    return formatResidentDisplayName(resident, fallback);
  }

  const givenNames = [firstName, middleName].filter(Boolean).join(' ').trim();
  const baseName = givenNames ? `${lastName}, ${givenNames}` : lastName;

  return suffix ? `${baseName} ${suffix}` : baseName;
}

export function calculateAgeOnDate(
  birthDate: string | null | undefined,
  referenceDate: Date
) {
  if (!birthDate) {
    return null;
  }

  const date = parseDateValue(birthDate);

  if (!date) {
    return null;
  }

  let age = referenceDate.getFullYear() - date.getFullYear();
  const monthDiff = referenceDate.getMonth() - date.getMonth();

  if (
    monthDiff < 0 ||
    (monthDiff === 0 && referenceDate.getDate() < date.getDate())
  ) {
    age -= 1;
  }

  return age >= 0 ? age : null;
}

export function formatFriendlyDateTime(value: string | null | undefined) {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return null;
  }

  const datePart = new Intl.DateTimeFormat('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(date);

  const timePart = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  }).format(date);

  return `${datePart} at ${timePart}`;
}

export function formatFriendlyTime(value: string | null | undefined) {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
  }).format(date);
}

export function formatFriendlyDate(value: string | null | undefined) {
  if (!value) {
    return null;
  }

  const date = parseDateOnly(value);

  if (date) {
    return new Intl.DateTimeFormat('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    }).format(date);
  }

  const parsed = parseDateValue(value);

  if (!parsed) {
    return null;
  }

  return new Intl.DateTimeFormat('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  }).format(parsed);
}

export function formatBirthDateInput(value: string | null | undefined) {
  const digits = (value ?? '').replace(/\D/g, '').slice(0, 8);

  if (digits.length <= 4) {
    return digits;
  }

  if (digits.length <= 6) {
    return `${digits.slice(0, 4)}/${digits.slice(4)}`;
  }

  return `${digits.slice(0, 4)}/${digits.slice(4, 6)}/${digits.slice(6, 8)}`;
}

export function normalizeBirthDateInput(value: string | null | undefined) {
  const digits = (value ?? '').replace(/\D/g, '');

  if (digits.length !== 8) {
    return null;
  }

  const year = Number(digits.slice(0, 4));
  const month = Number(digits.slice(4, 6));
  const day = Number(digits.slice(6, 8));

  const date = new Date(year, month - 1, day);

  if (
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null;
  }

  return `${year}-${pad(month)}-${pad(day)}`;
}

export function birthDateInputFromServer(value: string | null | undefined) {
  if (!value) {
    return '';
  }

  return formatBirthDateInput(value);
}

export function datePickerValueFromInput(value: string | null | undefined) {
  const normalized = normalizeBirthDateInput(value);

  if (!normalized) {
    return new Date(2000, 0, 1);
  }

  const [year, month, day] = normalized.split('-').map(Number);

  return new Date(year, month - 1, day);
}

export function dateInputFromPicker(date: Date) {
  return `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())}`;
}

export function formatPurokLabel(
  displayName: string | null | undefined,
  purokId: number | null | undefined,
  fallback = 'Purok not available'
) {
  if (displayName) {
    return displayName;
  }

  if (purokId) {
    return `Purok ${purokId}`;
  }

  return fallback;
}

export function humanizeLastSync(value: string | null | undefined) {
  return formatFriendlyDateTime(value) ?? DATE_INPUT_PLACEHOLDER;
}

export function calculateAgeFromBirthDate(value: string | null | undefined) {
  return calculateAgeOnDate(value, new Date());
}

export function daysSinceDate(value: string | null | undefined) {
  if (!value) {
    return null;
  }

  const date = parseDateValue(value);

  if (!date) {
    return null;
  }

  const now = startOfLocalDay(new Date());
  const comparisonDate = startOfLocalDay(date);
  const diffMs = now.getTime() - comparisonDate.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  return diffDays >= 0 ? diffDays : null;
}
