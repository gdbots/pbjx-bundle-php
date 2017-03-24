import $ from 'jquery';

class DynamicFieldComponent {
  constructor(options) {
    this.$el = $(`[data-dynamic-field=${options.widgetId}]`);
    this.options = options || {};

    this.initialize(options);
  }

  /**
   * @constructor
   * @param {Object} options
   */
  initialize(options) {
    _bindEvent.bind(this)();
  }
}

/**
 * Binds widget instance to environment events.
 *
 * @protected
 */
function _bindEvent() {
  const self = this;

  self.$el.find('.js-dynamic-field-kind').on('change', e => {
    self.$el.find('.js-dynamic-field-option').hide();
    self.$el.find('.js-dynamic-field-option :input').attr('disabled', true)

    const $field = $(e.target).closest('.row').find(`.js-dynamic-field-option :input[id$=_${$(e.target).val()}]`);
    $field.off('change');
    $field.on('change', (fe) => {
      self.$el.find('.js-dynamic-field-value')
        .val($(fe.target).val());
    });
    $field.attr('disabled', false).change();
    $field.closest('.js-dynamic-field-option').show();
  })

  // run on load first time
  .change();
};

export default DynamicFieldComponent;
