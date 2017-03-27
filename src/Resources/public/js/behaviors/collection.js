import $ from 'jquery';

/**
 * Returns the collection item
 *
 * @param {Object} collectionInfo
 *
 * @return {String}
 *
 * @protected
 */
function getCollectionNextItemHtml(collectionInfo) {
  return collectionInfo.prototypeHtml.replace(new RegExp(collectionInfo.prototypeName, 'g'), collectionInfo.nextIndex);
}

/**
 * Get collection prototype settings.
 *
 * @return {Object}
 *
 * @protected
 */
function getCollectionInfo($el) {
  const index = $el.data('last-index') || $el.children().length;
  const prototypeName = $el.attr('data-prototype-name') || '__name__';
  const html = $el.attr('data-prototype');

  return {
    nextIndex: index,
    prototypeHtml: html,
    prototypeName,
  };
}

/**
 * Binds widget instance to environment events.
 *
 * @protected
 */
function bindEvent() {
  const self = this;

  self.$el.parent('.row-collection').on('click', '.js-btn-add-collection-item-btn', (e) => {
    e.preventDefault();

    if ($(e.target).attr('disabled')) {
      return;
    }

    const rowCountAdd = self.$el.data('row-count-add') || 1;
    const collectionInfo = getCollectionInfo(self.$el);

    for (let i = 1; i <= rowCountAdd; i++) {
      const nextItemHtml = getCollectionNextItemHtml(collectionInfo);
      collectionInfo.nextIndex++;
      self.$el.append(nextItemHtml)
        .trigger('content:changed')
        .data('last-index', collectionInfo.nextIndex);
    }

    self.$el.find('input.position-input').each((i, el) => {
      $(el).val(i);
    });
  });

  self.$el.parent('.row-collection').on('click', '.js-btn-remove-collection-item-btn', (e) => {
    e.preventDefault();

    if ($(e.target).attr('disabled')) {
      return;
    }

    let closest = '*[data-content]';
    if ($(e.target).data('closest')) {
      closest = $(e.target).data('closest');
    }

    const item = $(e.target).closest(closest);
    item.trigger('content:remove')
      .remove();
  });
}

export default class CollectionComponent {
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
    bindEvent.bind(this)();
  }
}
