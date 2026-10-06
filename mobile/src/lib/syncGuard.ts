// Keep local mutations out of snapshot/refresh transactions, including across awaits.
let tail: Promise<unknown> = Promise.resolve();
let editors = 0;

export function serializeLocalWrite<T>(operation: () => Promise<T>): Promise<T> {
  const result = tail.then(operation);
  tail = result.catch(() => undefined);
  return result;
}

export function registerLocalEditor() {
  editors += 1;
  return () => { editors -= 1; };
}

export function hasLocalEditor() {
  return editors > 0;
}

export class RefreshDeferredError extends Error {
  constructor() {
    super('Local work must be saved and uploaded before refreshing.');
    this.name = 'RefreshDeferredError';
  }
}

export class AccountSwitchBlockedError extends Error {
  constructor() {
    super('This device still has unsynced records from the previous account. Sign back in with that account and sync them before using another account.');
    this.name = 'AccountSwitchBlockedError';
  }
}

export class DatasetOwnershipError extends Error {
  constructor() {
    super('Please sign back in with the account that saved the records on this device.');
    this.name = 'DatasetOwnershipError';
  }
}

export class AssignmentChangedError extends Error {
  constructor() {
    super('Your field assignment changed. Existing unsynced work must be resolved before this device can load the new assignment. Please contact an administrator.');
    this.name = 'AssignmentChangedError';
  }
}
