define(
[
  'jquery',
  'moment',
  'daterangepicker'
],
function ($, moment) {
  'use strict';

  return function(widgetId, widgetType, options, callback) {
    var $datepicker = $('#' + widgetId);

    $datepicker.change(function () {
      var $picker = $(this);
      if (!$picker.data('daterangepicker') || '' === $picker.val()) {
        return;
      }

      if (widgetType == 'date') {
        $picker.val(moment($picker.data('daterangepicker').startDate).utc().format('YYYY-MM-DD'));
      } else {
        $picker.val(moment($picker.data('daterangepicker').startDate).utc().format('YYYY-MM-DDTHH:mm:ss.SSSSSS') + 'Z');
      }
    });

    $datepicker.click(function () {
      var $picker = $(this);
      if ($picker.data('daterangepicker')) {
        return;
      }

      $picker.daterangepicker(
        $.extend(true, options || {}, {
          'startDate': '' !== $picker.val() ? moment.utc($picker.val()).local() : moment()
        }),
        callback || function(){}
      );

      $picker.click();
    });
  };
});
