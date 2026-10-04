<div class="navigation-skeleton p-4 sm:p-6 lg:p-8" data-navigation-skeleton-layout="generic" aria-hidden="true" hidden>
    <div class="navigation-skeleton-body">
        <div class="navigation-skeleton-line navigation-skeleton-title"></div>
        <div class="navigation-skeleton-line navigation-skeleton-subtitle"></div>
        <div class="navigation-skeleton-cards">
            @for($card = 0; $card < 3; $card++)
                <div class="navigation-skeleton-card">
                    <div class="navigation-skeleton-line"></div>
                    <div class="navigation-skeleton-line"></div>
                </div>
            @endfor
        </div>
        <div class="navigation-skeleton-table">
            @for($row = 0; $row < 6; $row++)
                <div class="navigation-skeleton-row">
                    <div class="navigation-skeleton-line"></div>
                    <div class="navigation-skeleton-line"></div>
                    <div class="navigation-skeleton-line"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
