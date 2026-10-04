export function certificateWizard(config) {
    return {
        ...config,
        step: config.step || 1,
        recipientType: config.recipientType || 'resident',
        residentId: String(config.residentId || ''),
        householdId: String(config.householdId || ''),
        usePrintedName: Boolean(config.usePrintedName),
        get recipient() {
            const options = this.recipientType === 'resident' ? this.residents : this.households;
            const id = this.recipientType === 'resident' ? this.residentId : this.householdId;
            return options.find(option => String(option.value) === id) || null;
        },
        get printedName() { return this.usePrintedName ? this.overrideName : this.recipient?.printedName || ''; },
        get localTimeLabel() {
            const date = new Date(`${this.issuedAt}+08:00`);
            return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('en-PH', {
                timeZone: 'Asia/Manila', dateStyle: 'long', timeStyle: 'short',
            }).format(date);
        },
        validStep(step) {
            if (step === 1) return ['barangay_clearance', 'certificate_of_indigency'].includes(this.certificateType) && Boolean(this.localTimeLabel);
            if (step === 2) return Boolean(this.recipient);
            if (step === 3) return Boolean(this.purpose.trim()) && Array.from(this.purpose).length <= 255
                && (!this.usePrintedName || (Boolean(this.overrideName.trim()) && Array.from(this.overrideName).length <= 255));
            return Boolean(this.officialSecretary) && [1, 2, 3].every(step => this.validStep(step));
        },
        go(step) {
            if (step > this.step && !this.validStep(this.step)) return;
            this.step = step;
            this.$nextTick?.(() => this.$refs?.stepHeading?.focus());
        },
        changeRecipientType(type) {
            this.recipientType = type;
            this.residentId = '';
            this.householdId = '';
            this.$dispatch?.('certificate-recipient-reset');
        },
        selectRecipient(detail) {
            if (detail.type !== this.recipientType) return;
            if (detail.type === 'resident') this.residentId = String(detail.id || '');
            else this.householdId = String(detail.id || '');
        },
        submit(event) {
            if (this.step !== 4 || !this.validStep(4) || !event.submitter?.hasAttribute('data-certificate-issue')) event.preventDefault();
        },
    };
}
