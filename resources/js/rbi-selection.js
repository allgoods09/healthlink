export function rbiHouseholdSelector(options = [], selected = []) {
    return {
        options,
        selected,
        query: '',
        page: 1,
        pageSize: 12,
        get matches() {
            const words = this.query.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
            return this.options.filter(option => words.every(word => option.label.toLocaleLowerCase().includes(word)));
        },
        get pageCount() {
            return Math.max(1, Math.ceil(this.matches.length / this.pageSize));
        },
        get visibleIds() {
            const page = Math.max(1, Math.min(this.page, this.pageCount));
            return this.matches.slice((page - 1) * this.pageSize, page * this.pageSize).map(option => option.id);
        },
    };
}
