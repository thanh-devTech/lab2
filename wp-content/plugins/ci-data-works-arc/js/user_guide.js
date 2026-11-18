(function ($) {
    function bindUserGuideHandlers() {
        var $helpSidebar = $('#helpSidebar');
        var $openHelpBtn = $('#openHelpBtn');
        var $closeHelpBtn = $('#closeHelpBtn');

        $(document).off('click.trpddUserGuide');

        if (!$helpSidebar.length || !$openHelpBtn.length || !$closeHelpBtn.length) {
            return;
        }

        $helpSidebar.removeClass('translate-x-0').addClass('translate-x-full');

        $openHelpBtn.off('click.trpddUserGuide').on('click.trpddUserGuide', function (event) {
            event.preventDefault();
            $helpSidebar.removeClass('translate-x-full').addClass('translate-x-0');
        });

        $closeHelpBtn.off('click.trpddUserGuide').on('click.trpddUserGuide', function (event) {
            event.preventDefault();
            $helpSidebar.removeClass('translate-x-0').addClass('translate-x-full');
        });

        $(document).on('click.trpddUserGuide', function (event) {
            if (
                !$(event.target).closest('#helpSidebar').length &&
                !$(event.target).closest('#openHelpBtn').length &&
                !$helpSidebar.hasClass('translate-x-full')
            ) {
                $helpSidebar.removeClass('translate-x-0').addClass('translate-x-full');
            }
        });
    }

    function refreshUserGuide($source) {
        var $newGuide = $source.find('#trpddUserGuideRoot').first();
        var $currentGuide = $('#trpddUserGuideRoot');

        if ($currentGuide.length) {
            $currentGuide.remove();
        }

        if ($newGuide.length) {
            $('body').append($newGuide);
        }

        bindUserGuideHandlers();
    }

    window.trpddInitUserGuide = bindUserGuideHandlers;
    window.trpddRefreshUserGuide = refreshUserGuide;

    $(bindUserGuideHandlers);
})(jQuery);
