const DATE_INPUT_PLACEHOLDER = 'YYYY/MM/DD';

function pad(value: number) {
  return String(value).padStart(2, '0');
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

export function formatFriendlyDate(value: string | null | undefined) {
  if (!value) {
    return null;
  }

  const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (match) {
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

    return new Intl.DateTimeFormat('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    }).format(date);
  }

  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
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
