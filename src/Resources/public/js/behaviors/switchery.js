/*
  Require libraries:
    - http://abpetkov.github.io/switchery/
*/

import $ from 'jquery';
import Switchery from 'switchery';
import SwitcheryManager from '../switchery-manager';

class SwitcheryComponent {
  constructor(options) {
    this.$el = $(`#${options.widgetId}`);
    this.options = options || {};

    this.initialize(options);
  }

  /**
   * @constructor
   * @param {Object} options
   */
  initialize(options) {
    const switchery = new Switchery(this.$el[0], options.widgetOptions);
    this.$el.data('switchery', switchery);

    SwitcheryManager.addInstance(options.widgetId, switchery);
  }
}

export default SwitcheryComponent;
