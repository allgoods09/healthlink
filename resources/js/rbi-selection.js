export function rbiHouseholdSelector(options = [], selected = []) {
    const searchOptions = options.map(option => ({ option, text: option.label.toLocaleLowerCase() }));

    return {
        options,
        selected,
        query: '',
        page: 1,
        pageSize: 12,
        matches: [],
        pageCount: 1,
        visibleIds: [],
        init() {
            this.updateSearch();
        },
        updateSearch() {
            const words = this.query.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
            this.matches = searchOptions.filter(({ text }) => words.every(word => text.includes(word))).map(({ option }) => option);
            this.page = 1;
            this.pageCount = Math.max(1, Math.ceil(this.matches.length / this.pageSize));
            this.updatePage();
        },
        changePage(step) {
            this.page = Math.max(1, Math.min(this.page + step, this.pageCount));
            this.updatePage();
        },
        updatePage() {
            const page = Math.max(1, Math.min(this.page, this.pageCount));
            this.visibleIds = this.matches.slice((page - 1) * this.pageSize, page * this.pageSize).map(option => option.id);
        },
    };
}
