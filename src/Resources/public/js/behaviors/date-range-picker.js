/*
  Require libraries:
    - http://momentjs.com/
    - http://www.daterangepicker.com/
*/

define(
[
  'jquery',
  'moment',
  'daterangepicker'
],
function ($, moment) {
  'use strict';

  var DateRangePickerComponent = function(options) {
    this.$el = $('#' + options.widgetId);
    this.options = options || {};

    this.initialize(options);
  };

  /**
   * @constructor
   * @param {Object} options
   */
  DateRangePickerComponent.prototype.initialize = function(options) {
    _bindEvent.bind(this)();
  };

  /**
   * Binds widget instance to environment events.
   *
   * @protected
   */
  var _bindEvent = function() {
    var self = this;

    self.$el.change(function () {
      var $picker = $(this);
      if (!$picker.data('daterangepicker') || '' === $picker.val()) {
        return;
      }

      if (self.options.widgetType == 'date') {
        $picker.val(moment($picker.data('daterangepicker').startDate).format('YYYY-MM-DD'));
      } else {
        $picker.val(moment($picker.data('daterangepicker').startDate).utc().format('YYYY-MM-DDTHH:mm:ss.SSSSSS') + 'Z');
      }
    });

    self.$el.click(function () {
      var $picker = $(this);
      if ($picker.data('daterangepicker')) {
        return;
      }

      var startDate = moment().seconds(0);
      var minutes = startDate.minutes();
      if (minutes < 60) startDate.minutes(45);
      if (minutes < 45) startDate.minutes(30);
      if (minutes < 30) startDate.minutes(15);
      if (minutes < 15) startDate.minutes(0);

      if ('' !== $picker.val()) {
        startDate = self.options.widgetType == 'date'
          ? $picker.val()
          : moment.utc($picker.val()).local()
      }

      $picker.daterangepicker(
        $.extend(true, self.options.widgetOptions, {
          'startDate': startDate
        }),
        self.options.widgetCallback
      );

      $picker.click();
    });
  };

  return DateRangePickerComponent;
});
