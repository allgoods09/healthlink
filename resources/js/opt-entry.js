export function optCaregiverField(config = {}) {
    return {
        options: config.options ?? [], name: config.name ?? '',
        residentId: config.residentId ? String(config.residentId) : '',
        isOpen: false, highlightedIndex: -1,
        get matches() {
            const search = this.name.trim().toLowerCase();
            return search ? this.options.filter(option => option.label.toLowerCase().includes(search)).slice(0, 8) : [];
        },
        init() {
            if (!this.options.some(option => String(option.value) === this.residentId)) this.residentId = '';
        },
        input() {
            this.residentId = ''; this.highlightedIndex = -1;
            this.isOpen = this.matches.length > 0;
        },
        move(step) {
            if (!this.matches.length) return;
            this.isOpen = true;
            this.highlightedIndex = (this.highlightedIndex + step + this.matches.length) % this.matches.length;
        },
        choose(option) {
            if (!option) return;
            this.name = option.label; this.residentId = String(option.value);
            this.isOpen = false; this.highlightedIndex = -1;
        },
    };
}

export function optMeasurementForm(config = {}) {
    return {
        date: config.date, posture: config.posture, methodChanged: Boolean(config.recorded),
        updateDefaultMethod() {
            if (this.methodChanged || !this.date) return;
            const [year, month, day] = this.date.split('-').map(Number);
            const [birthYear, birthMonth, birthDay] = config.dob.split('-').map(Number);
            const months = (year - birthYear) * 12 + month - birthMonth - (day < birthDay ? 1 : 0);
            this.posture = months < 24 ? 'recumbent' : 'standing';
        },
    };
}
