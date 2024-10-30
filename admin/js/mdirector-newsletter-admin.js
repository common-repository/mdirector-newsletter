jQuery(document).ready(function ($) {
    'use strict';

    // Timepicker
    $('input.timepicker').timepicker({
        timeFormat: 'HH:mm',
        minTime: new Date(0, 0, 0, 0, 0, 0),
        maxTime: new Date(0, 0, 0, 23, 30, 0),
        startTime: new Date(0, 0, 0, 0, 0, 0),
        interval: 30
    });

    $('.dynamic-choice').on( 'click', function () {
        const $this = $(this),
            isChecked = $this.is(':checked');

        $this.parents('.choice-block').siblings('.choice-block').find('.subject-block')
            .toggleClass('disabled', isChecked)
            .find('input, select').prop('readonly', isChecked);

        $this.siblings('.subject-block')
            .toggleClass('disabled', !isChecked)
            .find('input, select').prop('readonly', !isChecked);
    });

    $('[data-toggle]').on('click', function() {
        const $this = $(this);
        $('#' + $this.data('toggle')).toggle($this.is(':checked'));
    } );

    const initSchedulerLists = () => {
        const $lists = $('.md-user-lists-wrapper');
        const $checkboxes = $lists.find(':checkbox');

        $checkboxes.on('change', (ele) => {
            const $item = $(ele.currentTarget),
                itemState = $item.is(':checked'),
                $list = $item.closest('li'),
                type = $list.attr('data-type');

            if (type === 'list') {
                toggleAllGroupsAndSegmentsInList($list, itemState);
            } else if (type === 'group') {
                toggleSegmentsInGroup($list, itemState);
            }
        });

        const toggleAllGroupsAndSegmentsInList = ($list, itemState) => {
            const $items = $list.find(':checkbox').not(':first');

            $items.attr({'disabled': itemState });
            itemState && $items.removeAttr('checked');
        };

        const toggleSegmentsInGroup = ($group, itemState) => {
            const segmentsRelated = $group.attr('data-related'),
                $listParent = $group.closest('.list'),
                $segmentsContainer = $listParent.find('.segment');

            segmentsRelated.split(';').forEach((ele) => {
                const $segmentsMatches = $segmentsContainer.filter('[data-value="' + ele + '"]').find(':checkbox');
                $segmentsMatches.attr({'disabled': itemState });
                itemState && $segmentsMatches.removeAttr('checked');
            });
        };
    };

    const initValidator = (e) => {
        e.preventDefault();

        const $schedulerForm = $('#mdirector_scheduler_delivery_id');
        const $inputs = $schedulerForm.find('input[type="text"]');
        const $lists = $('input[name="mdirector-scheduler-lists[]"');

        let errors = false;

        $inputs.each(function () {
            const $input = $(this);

            if (!$input.val()) {
                $input.addClass('required');
                errors = true;
            } else {
                $input.removeClass('required');
            }
        });

        if (!$lists.filter(':checked').length) {
            $('.md-user-lists-container').find('.md-user-lists-label').addClass('required');
            errors = true;
        } else {
            $('.md-user-lists-container').find('.md-user-lists-label').removeClass('required');
        }

        if (errors) {
            return false;
        }

        $('#mdirector-scheduler-send').val('mdirector-scheduler-send');
        $('#post').submit();
    };

    const checkForValidator = (e) => {
        const $mdSchedulerIsActive = $('input[name="mdirector_scheduler_active"]');

        if ($mdSchedulerIsActive.is(':checked')) {
            return initValidator(e);
        }

        return true;
    };

    const initSchedulerValidator = () => {
        const $mdSchedulerSubmitButton = $('#mdscheduler_send_now');
        $mdSchedulerSubmitButton.on('click', initValidator);

        const $wpSubmitButton = $('#publish');
        $wpSubmitButton.on('click', checkForValidator);
    };

    initSchedulerLists();
    initSchedulerValidator();
});
