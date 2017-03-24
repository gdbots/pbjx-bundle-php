import $ from 'jquery';

/**
 * Error handler:
 *  - in production mode shows user friendly message
 *  - in dev mode output in console expanded stack trace and throws the error
 *
 * @param {string} message
 * @param {Error} error
 *
 * @protected
 */
function handleError(message, error) {
  const console = window.console;
  if (console && console.error) {
    console.error(message);
  } else {
    throw error;
  }
}

/**
 * Subscribes the view to content changes
 *  - on 'content:changed' event -- updates layout
 *  - on 'content:remove' event -- removes related components (if they are left undisposed)
 *
 * @protected
 */
function bindChangesEvents($elem) {
  const self = this;

  // if the container catches content changed event -- updates its layout
  $elem.on('content:changed', (e) => {
    if (e.isDefaultPrevented()) {
      return;
    }
    e.preventDefault();
    self.init();
  });

  // if the container catches content remove event -- disposes related components
  $elem.on('content:remove', (e) => {
    if (e.isDefaultPrevented()) {
      return;
    }
    e.preventDefault();
    $(e.target).find('[data-bound-component]').each((index, selector) => {
      const component = self.findComponent(selector);
      if (component) {
        component.remove();
      }
    });
  });
}

/**
 * Collect all elements that have components declaration.
 *
 * @param {Array.<jQuery>} elements
 * @param {Array.<string>} modules
 *
 * @protected
 */
function analyseDom(elements, modules) {
  const self = this;
  const el = self.$el[0];

  self.$el.find('[data-page-component-module]').each((index, selector) => {
    const $elem = $(selector);

    // optimize load time - push components to preload queue
    modules.push($elem.data('page-component-module'));

    // collects container elements
    elements.push($elem);

    bindChangesEvents.bind(self)($elem);
  });
}

/**
 * Read component's data attributes from the DOM element.
 *
 * @param {jQuery} $elem
 *
 * @protected
 */
function readData($elem) {
  const data = {
    module: $elem.data('page-component-module'),
    options: $elem.data('page-component-options') || {},
  };

  if (data.options.sourceElement) {
    data.options.sourceElement = $(data.options.sourceElement);
  } else {
    data.options.sourceElement = $elem;
  }

  const name = $elem.data('page-component-name') || $elem.attr('data-fid');
  if (name) {
    data.options.name = name;
  }
  return data;
}

/**
 * Cleanup trace of data attributes in the DOM element.
 *
 * @param {jQuery} $elem
 *
 * @protected
 */
function cleanupData($elem) {
  $elem
    .removeAttr('data-page-component-module')
    .removeAttr('data-page-component-options');
}

/**
 * Handles component load success:
 *  - initializes the component
 *  - add the component to registry
 *
 * @param {jQuery.Deferred} initDeferred
 * @param {Object} options
 * @param {Function} Component
 *
 * @protected
 */
function onComponentLoaded(initDeferred, options, Component) {
  if (this.removed) {
    initDeferred.resolve();
    return;
  }

  const $elem = options.sourceElement;
  const name = options.name;

  if (name && this.components.name !== undefined) {
    const message = `Component with the name "${name}" is already registered in the layout`;
    handleError.bind(this)(message, new Error(message));

    // prevent interface from blocking by loader
    initDeferred.resolve();
    return;
  }

  if (Component.__esModule) {
    Component = Component.default;
  }

  const component = new Component(options);
  initDeferred.resolve(component);
}

/**
 * Handles component load fail.
 *
 * @param {jQuery.Deferred} initDeferred
 * @param {Error} error
 *
 * @protected
 */
function onRequireJsError(initDeferred, error) {
  const message = `Cannot load module "${error.requireModules[0]}"`;
  handleError.bind(this)(message, error);
  // prevent interface from blocking by loader
  initDeferred.resolve();
}

/**
 * Initializes component for the element.
 *
 * @param {jQuery} $elem
 * @param {Object|null} options
 *
 * @returns {Promise}
 *
 * @protected
 */
function initComponent($elem, options) {
  const data = readData.bind(this)($elem);
  cleanupData.bind(this)($elem);

  // mark elem
  $elem.attr('data-bound-component', data.module);

  const initDeferred = $.Deferred();

  if (!data.options.sourceElement.get(0)) {
    const message = `Cannot resolve sourceElement by selector "${data.options.sourceElement.selector}"`;
    handleError.bind(this)(message, new Error(message));
    initDeferred.resolve();
  }

  const componentOptions = $.extend(true, {}, options || {}, data.options);

  // dynamic module load
  require.ensure([], () => {
    require(
      [`${data.module}`],
      $.proxy(onComponentLoaded, this, initDeferred, componentOptions),
      $.proxy(onRequireJsError, this, initDeferred),
    );
  });

  return initDeferred.promise();
}

export default class ComponentManager {
  constructor($el) {
    this.$el = $el;
    this.components = {};

    this.init();
  }

  init(options) {
    const promises = [];
    const elements = [];
    const modules = [];

    analyseDom.bind(this)(elements, modules);

    elements.forEach((element) => {
      promises.push(initComponent.bind(this)(element, options));
    });

    // optimize load time - preload components in separate layouts
    modules.forEach((module) => {
      require(`${module}`);
    });

    return $.when(...promises).then(() => {
      return arguments;
    });
  }

  /**
   * Getter for components.
   *
   * @param {string} name
   */
  get(name) {
    if (name in this.components) {
      return this.components[name].component;
    }

    return null;
  }

  /**
   * Getter/setter for components.
   *
   * @param {string} name
   * @param {BaseComponent} component to set
   * @param {HTMLElement} el
   */
  add(name, component, el) {
    if (this.removed) {
      // in case the manager already removed -- remove passed component as well
      component.remove();
      return;
    }
    this.remove(name);
    this.components[name] = {
      component,
      el,
    };
  }

  /**
   * Removes the component.
   *
   * @param {string} name component name to remove
   */
  remove(name) {
    const item = this.components[name];
    delete this.components[name];
    if (item) {
      item.component.remove();
    }
  }

  /**
   * Destroys all linked components.
   */
  removeAll() {
    Object.keys(this.components).forEach((name) => {
      this.remove(this.components[name]);
    });
  }

  /**
   * Removes component manager.
   */
  destroy() {
    this.removeAll();
    delete this.$el;
    this.removed = true;
    return typeof Object.freeze === 'function' ? Object.freeze(this) : false;
  }

  /**
   * Find component related to the element.
   *
   * @param {HTMLElement} el
   *
   * @returns {BaseComponent}
   */
  findComponent(el) {
    let found = false;
    Object.keys(this.components).forEach((name) => {
      const item = this.components[name];
      if (item.el === el) {
        found = item;
      }
    });
    if (found) {
      return found.component;
    }
  }

  /**
   * Applies callback function to all component in the collection.
   *
   * @param {Function} callback
   * @param {Object} context
   */
  forEachComponent(callback, context) {
    Object.keys(this.components).forEach((name) => {
      callback.apply(context, [this.components[name].component]);
    });
  }
}
