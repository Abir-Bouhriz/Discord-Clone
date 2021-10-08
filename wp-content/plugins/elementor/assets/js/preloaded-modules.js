/*! elementor - v3.4.4 - 13-09-2021 */
(self["webpackChunkelementor"] = self["webpackChunkelementor"] || []).push([["preloaded-modules"],{

/***/ "../assets/dev/js/frontend/handlers/accordion.js":
/*!*******************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/accordion.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, exports, __webpack_require__) => {

"use strict";


var _interopRequireDefault = __webpack_require__(/*! @babel/runtime/helpers/interopRequireDefault */ "../node_modules/@babel/runtime/helpers/interopRequireDefault.js");

Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

var _baseTabs = _interopRequireDefault(__webpack_require__(/*! ./base-tabs */ "../assets/dev/js/frontend/handlers/base-tabs.js"));

class Accordion extends _baseTabs.default {
  getDefaultSettings() {
    const defaultSettings = super.getDefaultSettings();
    return { ...defaultSettings,
      showTabFn: 'slideDown',
      hideTabFn: 'slideUp'
    };
  }

}

exports.default = Accordion;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/alert.js":
/*!***************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/alert.js ***!
  \***************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

class Alert extends elementorModules.frontend.handlers.Base {
  getDefaultSettings() {
    return {
      selectors: {
        dismissButton: '.elementor-alert-dismiss'
      }
    };
  }

  getDefaultElements() {
    const selectors = this.getSettings('selectors');
    return {
      $dismissButton: this.$element.find(selectors.dismissButton)
    };
  }

  bindEvents() {
    this.elements.$dismissButton.on('click', this.onDismissButtonClick.bind(this));
  }

  onDismissButtonClick() {
    this.$element.fadeOut();
  }

}

exports.default = Alert;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/base-tabs.js":
/*!*******************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/base-tabs.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

class baseTabs extends elementorModules.frontend.handlers.Base {
  getDefaultSettings() {
    return {
      selectors: {
        tablist: '[role="tablist"]',
        tabTitle: '.elementor-tab-title',
        tabContent: '.elementor-tab-content'
      },
      classes: {
        active: 'elementor-active'
      },
      showTabFn: 'show',
      hideTabFn: 'hide',
      toggleSelf: true,
      hidePrevious: true,
      autoExpand: true,
      keyDirection: {
        ArrowLeft: elementorFrontendConfig.is_rtl ? 1 : -1,
        ArrowUp: -1,
        ArrowRight: elementorFrontendConfig.is_rtl ? -1 : 1,
        ArrowDown: 1
      }
    };
  }

  getDefaultElements() {
    const selectors = this.getSettings('selectors');
    return {
      $tabTitles: this.findElement(selectors.tabTitle),
      $tabContents: this.findElement(selectors.tabContent)
    };
  }

  activateDefaultTab() {
    const settings = this.getSettings();

    if (!settings.autoExpand || 'editor' === settings.autoExpand && !this.isEdit) {
      return;
    }

    const defaultActiveTab = this.getEditSettings('activeItemIndex') || 1,
          originalToggleMethods = {
      showTabFn: settings.showTabFn,
      hideTabFn: settings.hideTabFn
    }; // Toggle tabs without animation to avoid jumping

    this.setSettings({
      showTabFn: 'show',
      hideTabFn: 'hide'
    });
    this.changeActiveTab(defaultActiveTab); // Return back original toggle effects

    this.setSettings(originalToggleMethods);
  }

  handleKeyboardNavigation(event) {
    const tab = event.currentTarget,
          $tabList = jQuery(tab.closest(this.getSettings('selectors').tablist)),
          $tabs = $tabList.find(this.getSettings('selectors').tabTitle),
          isVertical = 'vertical' === $tabList.attr('aria-orientation');

    switch (event.key) {
      case 'ArrowLeft':
      case 'ArrowRight':
        if (isVertical) {
          return;
        }

        break;

      case 'ArrowUp':
      case 'ArrowDown':
        if (!isVertical) {
          return;
        }

        event.preventDefault();
        break;

      case 'Home':
        event.preventDefault();
        $tabs.first().focus();
        return;

      case 'End':
        event.preventDefault();
        $tabs.last().focus();
        return;

      default:
        return;
    }

    const tabIndex = tab.getAttribute('data-tab') - 1,
          direction = this.getSettings('keyDirection')[event.key],
          nextTab = $tabs[tabIndex + direction];

    if (nextTab) {
      nextTab.focus();
    } else if (-1 === tabIndex + direction) {
      $tabs.last().focus();
    } else {
      $tabs.first().focus();
    }
  }

  deactivateActiveTab(tabIndex) {
    const settings = this.getSettings(),
          activeClass = settings.classes.active,
          activeFilter = tabIndex ? '[data-tab="' + tabIndex + '"]' : '.' + activeClass,
          $activeTitle = this.elements.$tabTitles.filter(activeFilter),
          $activeContent = this.elements.$tabContents.filter(activeFilter);
    $activeTitle.add($activeContent).removeClass(activeClass);
    $activeTitle.attr({
      tabindex: '-1',
      'aria-selected': 'false',
      'aria-expanded': 'false'
    });
    $activeContent[settings.hideTabFn]();
    $activeContent.attr('hidden', 'hidden');
  }

  activateTab(tabIndex) {
    const settings = this.getSettings(),
          activeClass = settings.classes.active,
          $requestedTitle = this.elements.$tabTitles.filter('[data-tab="' + tabIndex + '"]'),
          $requestedContent = this.elements.$tabContents.filter('[data-tab="' + tabIndex + '"]'),
          animationDuration = 'show' === settings.showTabFn ? 0 : 400;
    $requestedTitle.add($requestedContent).addClass(activeClass);
    $requestedTitle.attr({
      tabindex: '0',
      'aria-selected': 'true',
      'aria-expanded': 'true'
    });
    $requestedContent[settings.showTabFn](animationDuration, () => elementorFrontend.elements.$window.trigger('resize'));
    $requestedContent.removeAttr('hidden');
  }

  isActiveTab(tabIndex) {
    return this.elements.$tabTitles.filter('[data-tab="' + tabIndex + '"]').hasClass(this.getSettings('classes.active'));
  }

  bindEvents() {
    this.elements.$tabTitles.on({
      keydown: event => {
        // Support for old markup that includes an `<a>` tag in the tab
        if (jQuery(event.target).is('a') && `Enter` === event.key) {
          event.preventDefault();
        } // We listen to keydowon event for these keys in order to prevent undesired page scrolling


        if (['End', 'Home', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
          this.handleKeyboardNavigation(event);
        }
      },
      keyup: event => {
        switch (event.key) {
          case 'ArrowLeft':
          case 'ArrowRight':
            this.handleKeyboardNavigation(event);
            break;

          case 'Enter':
          case 'Space':
            event.preventDefault();
            this.changeActiveTab(event.currentTarget.getAttribute('data-tab'));
            break;
        }
      },
      click: event => {
        event.preventDefault();
        this.changeActiveTab(event.currentTarget.getAttribute('data-tab'));
      }
    });
  }

  onInit(...args) {
    super.onInit(...args);
    this.activateDefaultTab();
  }

  onEditSettingsChange(propertyName) {
    if ('activeItemIndex' === propertyName) {
      this.activateDefaultTab();
    }
  }

  changeActiveTab(tabIndex) {
    const isActiveTab = this.isActiveTab(tabIndex),
          settings = this.getSettings();

    if ((settings.toggleSelf || !isActiveTab) && settings.hidePrevious) {
      this.deactivateActiveTab();
    }

    if (!settings.hidePrevious && isActiveTab) {
      this.deactivateActiveTab(tabIndex);
    }

    if (!isActiveTab) {
      this.activateTab(tabIndex);
    }
  }

}

exports.default = baseTabs;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/counter.js":
/*!*****************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/counter.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

class Counter extends elementorModules.frontend.handlers.Base {
  getDefaultSettings() {
    return {
      selectors: {
        counterNumber: '.elementor-counter-number'
      }
    };
  }

  getDefaultElements() {
    const selectors = this.getSettings('selectors');
    return {
      $counterNumber: this.$element.find(selectors.counterNumber)
    };
  }

  onInit() {
    super.onInit();
    elementorFrontend.waypoint(this.elements.$counterNumber, () => {
      const data = this.elements.$counterNumber.data(),
            decimalDigits = data.toValue.toString().match(/\.(.*)/);

      if (decimalDigits) {
        data.rounding = decimalDigits[1].length;
      }

      this.elements.$counterNumber.numerator(data);
    });
  }

}

exports.default = Counter;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/image-carousel.js":
/*!************************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/image-carousel.js ***!
  \************************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

class ImageCarousel extends elementorModules.frontend.handlers.SwiperBase {
  getDefaultSettings() {
    return {
      selectors: {
        carousel: '.elementor-image-carousel-wrapper',
        slideContent: '.swiper-slide'
      }
    };
  }

  getDefaultElements() {
    const selectors = this.getSettings('selectors');
    const elements = {
      $swiperContainer: this.$element.find(selectors.carousel)
    };
    elements.$slides = elements.$swiperContainer.find(selectors.slideContent);
    return elements;
  }

  getSwiperSettings() {
    const elementSettings = this.getElementSettings(),
          slidesToShow = +elementSettings.slides_to_show || 3,
          isSingleSlide = 1 === slidesToShow,
          elementorBreakpoints = elementorFrontend.config.responsive.activeBreakpoints,
          defaultSlidesToShowMap = {
      mobile: 1,
      tablet: isSingleSlide ? 1 : 2
    };
    const swiperOptions = {
      slidesPerView: slidesToShow,
      loop: 'yes' === elementSettings.infinite,
      speed: elementSettings.speed,
      handleElementorBreakpoints: true
    };
    swiperOptions.breakpoints = {};
    let lastBreakpointSlidesToShowValue = slidesToShow;
    Object.keys(elementorBreakpoints).reverse().forEach(breakpointName => {
      // Tablet has a specific default `slides_to_show`.
      const defaultSlidesToShow = defaultSlidesToShowMap[breakpointName] ? defaultSlidesToShowMap[breakpointName] : lastBreakpointSlidesToShowValue;
      swiperOptions.breakpoints[elementorBreakpoints[breakpointName].value] = {
        slidesPerView: +elementSettings['slides_to_show_' + breakpointName] || defaultSlidesToShow,
        slidesPerGroup: +elementSettings['slides_to_scroll_' + breakpointName] || 1
      };
      lastBreakpointSlidesToShowValue = +elementSettings['slides_to_show_' + breakpointName] || defaultSlidesToShow;
    });

    if ('yes' === elementSettings.autoplay) {
      swiperOptions.autoplay = {
        delay: elementSettings.autoplay_speed,
        disableOnInteraction: 'yes' === elementSettings.pause_on_interaction
      };
    }

    if (isSingleSlide) {
      swiperOptions.effect = elementSettings.effect;

      if ('fade' === elementSettings.effect) {
        swiperOptions.fadeEffect = {
          crossFade: true
        };
      }
    } else {
      swiperOptions.slidesPerGroup = +elementSettings.slides_to_scroll || 1;
    }

    if (elementSettings.image_spacing_custom) {
      swiperOptions.spaceBetween = elementSettings.image_spacing_custom.size;
    }

    const showArrows = 'arrows' === elementSettings.navigation || 'both' === elementSettings.navigation,
          showDots = 'dots' === elementSettings.navigation || 'both' === elementSettings.navigation;

    if (showArrows) {
      swiperOptions.navigation = {
        prevEl: '.elementor-swiper-button-prev',
        nextEl: '.elementor-swiper-button-next'
      };
    }

    if (showDots) {
      swiperOptions.pagination = {
        el: '.swiper-pagination',
        type: 'bullets',
        clickable: true
      };
    }

    return swiperOptions;
  }

  async onInit(...args) {
    super.onInit(...args);
    const elementSettings = this.getElementSettings();

    if (!this.elements.$swiperContainer.length || 2 > this.elements.$slides.length) {
      return;
    }

    const Swiper = elementorFrontend.utils.swiper;
    this.swiper = await new Swiper(this.elements.$swiperContainer, this.getSwiperSettings()); // Expose the swiper instance in the frontend

    this.elements.$swiperContainer.data('swiper', this.swiper);

    if ('yes' === elementSettings.pause_on_hover) {
      this.togglePauseOnHover(true);
    }
  }

  updateSwiperOption(propertyName) {
    const elementSettings = this.getElementSettings(),
          newSettingValue = elementSettings[propertyName],
          params = this.swiper.params; // Handle special cases where the value to update is not the value that the Swiper library accepts.

    switch (propertyName) {
      case 'image_spacing_custom':
        params.spaceBetween = newSettingValue.size || 0;
        break;

      case 'autoplay_speed':
        params.autoplay.delay = newSettingValue;
        break;

      case 'speed':
        params.speed = newSettingValue;
        break;
    }

    this.swiper.update();
  }

  getChangeableProperties() {
    return {
      pause_on_hover: 'pauseOnHover',
      autoplay_speed: 'delay',
      speed: 'speed',
      image_spacing_custom: 'spaceBetween'
    };
  }

  onElementChange(propertyName) {
    const changeableProperties = this.getChangeableProperties();

    if (changeableProperties[propertyName]) {
      // 'pause_on_hover' is implemented by the handler with event listeners, not the Swiper library.
      if ('pause_on_hover' === propertyName) {
        const newSettingValue = this.getElementSettings('pause_on_hover');
        this.togglePauseOnHover('yes' === newSettingValue);
      } else {
        this.updateSwiperOption(propertyName);
      }
    }
  }

  onEditSettingsChange(propertyName) {
    if ('activeItemIndex' === propertyName) {
      this.swiper.slideToLoop(this.getEditSettings('activeItemIndex') - 1);
    }
  }

}

exports.default = ImageCarousel;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/progress.js":
/*!******************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/progress.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

class Progress extends elementorModules.frontend.handlers.Base {
  getDefaultSettings() {
    return {
      selectors: {
        progressNumber: '.elementor-progress-bar'
      }
    };
  }

  getDefaultElements() {
    const selectors = this.getSettings('selectors');
    return {
      $progressNumber: this.$element.find(selectors.progressNumber)
    };
  }

  onInit() {
    super.onInit();
    elementorFrontend.waypoint(this.elements.$progressNumber, () => {
      const $progressbar = this.elements.$progressNumber;
      $progressbar.css('width', $progressbar.data('max') + '%');
    });
  }

}

exports.default = Progress;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/tabs.js":
/*!**************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/tabs.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, exports, __webpack_require__) => {

"use strict";


var _interopRequireDefault = __webpack_require__(/*! @babel/runtime/helpers/interopRequireDefault */ "../node_modules/@babel/runtime/helpers/interopRequireDefault.js");

Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

var _baseTabs = _interopRequireDefault(__webpack_require__(/*! ./base-tabs */ "../assets/dev/js/frontend/handlers/base-tabs.js"));

class Tabs extends _baseTabs.default {
  getDefaultSettings() {
    const defaultSettings = super.getDefaultSettings();
    return { ...defaultSettings,
      toggleSelf: false
    };
  }

}

exports.default = Tabs;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/text-editor.js":
/*!*********************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/text-editor.js ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

class TextEditor extends elementorModules.frontend.handlers.Base {
  getDefaultSettings() {
    return {
      selectors: {
        paragraph: 'p:first'
      },
      classes: {
        dropCap: 'elementor-drop-cap',
        dropCapLetter: 'elementor-drop-cap-letter'
      }
    };
  }

  getDefaultElements() {
    const selectors = this.getSettings('selectors'),
          classes = this.getSettings('classes'),
          $dropCap = jQuery('<span>', {
      class: classes.dropCap
    }),
          $dropCapLetter = jQuery('<span>', {
      class: classes.dropCapLetter
    });
    $dropCap.append($dropCapLetter);
    return {
      $paragraph: this.$element.find(selectors.paragraph),
      $dropCap: $dropCap,
      $dropCapLetter: $dropCapLetter
    };
  }

  wrapDropCap() {
    const isDropCapEnabled = this.getElementSettings('drop_cap');

    if (!isDropCapEnabled) {
      // If there is an old drop cap inside the paragraph
      if (this.dropCapLetter) {
        this.elements.$dropCap.remove();
        this.elements.$paragraph.prepend(this.dropCapLetter);
        this.dropCapLetter = '';
      }

      return;
    }

    const $paragraph = this.elements.$paragraph;

    if (!$paragraph.length) {
      return;
    }

    const paragraphContent = $paragraph.html().replace(/&nbsp;/g, ' '),
          firstLetterMatch = paragraphContent.match(/^ *([^ ] ?)/);

    if (!firstLetterMatch) {
      return;
    }

    const firstLetter = firstLetterMatch[1],
          trimmedFirstLetter = firstLetter.trim(); // Don't apply drop cap when the content starting with an HTML tag

    if ('<' === trimmedFirstLetter) {
      return;
    }

    this.dropCapLetter = firstLetter;
    this.elements.$dropCapLetter.text(trimmedFirstLetter);
    const restoredParagraphContent = paragraphContent.slice(firstLetter.length).replace(/^ */, match => {
      return new Array(match.length + 1).join('&nbsp;');
    });
    $paragraph.html(restoredParagraphContent).prepend(this.elements.$dropCap);
  }

  onInit(...args) {
    super.onInit(...args);
    this.wrapDropCap();
  }

  onElementChange(propertyName) {
    if ('drop_cap' === propertyName) {
      this.wrapDropCap();
    }
  }

}

exports.default = TextEditor;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/toggle.js":
/*!****************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/toggle.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, exports, __webpack_require__) => {

"use strict";


var _interopRequireDefault = __webpack_require__(/*! @babel/runtime/helpers/interopRequireDefault */ "../node_modules/@babel/runtime/helpers/interopRequireDefault.js");

Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

var _baseTabs = _interopRequireDefault(__webpack_require__(/*! ./base-tabs */ "../assets/dev/js/frontend/handlers/base-tabs.js"));

class Toggle extends _baseTabs.default {
  getDefaultSettings() {
    const defaultSettings = super.getDefaultSettings();
    return { ...defaultSettings,
      showTabFn: 'slideDown',
      hideTabFn: 'slideUp',
      hidePrevious: false,
      autoExpand: 'editor'
    };
  }

}

exports.default = Toggle;

/***/ }),

/***/ "../assets/dev/js/frontend/handlers/video.js":
/*!***************************************************!*\
  !*** ../assets/dev/js/frontend/handlers/video.js ***!
  \***************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


Object.defineProperty(exports, "__esModule", ({
  value: true
}));
exports.default = void 0;

class Video extends elementorModules.frontend.handlers.Base {
  getDefaultSettings() {
    return {
      selectors: {
        imageOverlay: '.elementor-custom-embed-image-overlay',
        video: '.elementor-video',
        videoIframe: '.elementor-video-iframe'
      }
    };
  }

  getDefaultElements() {
    const selectors = this.getSettings('selectors');
    return {
      $imageOverlay: this.$element.find(selectors.imageOverlay),
      $video: this.$element.find(selectors.video),
      $videoIframe: this.$element.find(selectors.videoIframe)
    };
  }

  handleVideo() {
    if (this.getElementSettings('lightbox')) {
      return;
    }

    if ('youtube' === this.getElementSettings('video_type')) {
      this.apiProvider.onApiReady(apiObject => {
        this.elements.$imageOverlay.remove();
        this.prepareYTVideo(apiObject, true);
      });
    } else {
      this.elements.$imageOverlay.remove();
      this.playVideo();
    }
  }

  playVideo() {
    if (this.elements.$video.length) {
      // this.youtubePlayer exists only for YouTube videos, and its play function is different.
      if (this.youtubePlayer) {
        this.youtubePlayer.playVideo();
      } else {
        this.elements.$video[0].play();
      }

      return;
    }

    const $videoIframe = this.elements.$videoIframe,
          lazyLoad = $videoIframe.data('lazy-load');

    if (lazyLoad) {
      $videoIframe.attr('src', lazyLoad);
    }

    $videoIframe[0].src = this.apiProvider.getAutoplayURL($videoIframe[0].src);
  }

  async animateVideo() {
    const lightbox = await elementorFrontend.utils.lightbox;
    lightbox.setEntranceAnimation(this.getCurrentDeviceSetting('lightbox_content_animation'));
  }

  async handleAspectRatio() {
    const lightbox = await elementorFrontend.utils.lightbox;
    lightbox.setVideoAspectRatio(this.getElementSettings('aspect_ratio'));
  }

  async hideLightbox() {
    const lightbox = await elementorFrontend.utils.lightbox;
    lightbox.getModal().hide();
  }

  prepareYTVideo(YT, onOverlayClick) {
    const elementSettings = this.getElementSettings(),
          playerOptions = {
      videoId: this.videoID,
      events: {
        onReady: () => {
          if (elementSettings.mute) {
            this.youtubePlayer.mute();
          }

          if (elementSettings.autoplay || onOverlayClick) {
            this.youtubePlayer.playVideo();
          }
        },
        onStateChange: event => {
          if (event.data === YT.PlayerState.ENDED && elementSettings.loop) {
            this.youtubePlayer.seekTo(elementSettings.start || 0);
          }
        }
      },
      playerVars: {
        controls: elementSettings.controls ? 1 : 0,
        rel: elementSettings.rel ? 1 : 0,
        playsinline: elementSettings.play_on_mobile ? 1 : 0,
        modestbranding: elementSettings.modestbranding ? 1 : 0,
        autoplay: elementSettings.autoplay ? 1 : 0,
        start: elementSettings.start,
        end: elementSettings.end
      }
    }; // To handle CORS issues, when the default host is changed, the origin parameter has to be set.

    if (elementSettings.yt_privacy) {
      playerOptions.host = 'https://www.youtube-nocookie.com';
      playerOptions.origin = window.location.hostname;
    }

    this.youtubePlayer = new YT.Player(this.elements.$video[0], playerOptions);
  }

  bindEvents() {
    this.elements.$imageOverlay.on('click', this.handleVideo.bind(this));
  }

  onInit() {
    super.onInit();
    const elementSettings = this.getElementSettings();

    if (elementorFrontend.utils[elementSettings.video_type]) {
      this.apiProvider = elementorFrontend.utils[elementSettings.video_type];
    } else {
      this.apiProvider = elementorFrontend.utils.baseVideoLoader;
    }

    if ('youtube' !== elementSettings.video_type) {
      // Currently the only API integration in the Video widget is for the YT API
      return;
    }

    this.videoID = this.apiProvider.getVideoIDFromURL(elementSettings.youtube_url); // If there is an image overlay, the YouTube video prep method will be triggered on click

    if (!this.videoID) {
      return;
    } // If the user is using an image overlay, loading the API happens on overlay click instead of on init.


    if (elementSettings.show_image_overlay && elementSettings.image_overlay.url) {
      return;
    }

    if (elementSettings.lazy_load) {
      this.intersectionObserver = elementorModules.utils.Scroll.scrollObserver({
        callback: event => {
          if (event.isInViewport) {
            this.intersectionObserver.unobserve(this.elements.$video.parent()[0]);
            this.apiProvider.onApiReady(apiObject => this.prepareYTVideo(apiObject));
          }
        }
      }); // We observe the parent, since the video container has a height of 0.

      this.intersectionObserver.observe(this.elements.$video.parent()[0]);
      return;
    } // When Optimized asset loading is set to off, the video type is set to 'Youtube', and 'Privacy Mode' is set
    // to 'On', there might be a conflict with other videos that are loaded WITHOUT privacy mode, such as a
    // video bBackground in a section. In these cases, to avoid the conflict, a timeout is added to postpone the
    // initialization of the Youtube API object.


    if (!elementorFrontend.config.experimentalFeatures['e_optimized_assets_loading']) {
      setTimeout(() => {
        this.apiProvider.onApiReady(apiObject => this.prepareYTVideo(apiObject));
      }, 0);
    } else {
      this.apiProvider.onApiReady(apiObject => this.prepareYTVideo(apiObject));
    }
  }

  onElementChange(propertyName) {
    if (0 === propertyName.indexOf('lightbox_content_animation')) {
      this.animateVideo();
      return;
    }

    const isLightBoxEnabled = this.getElementSettings('lightbox');

    if ('lightbox' === propertyName && !isLightBoxEnabled) {
      this.hideLightbox();
      return;
    }

    if ('aspect_ratio' === propertyName && isLightBoxEnabled) {
      this.handleAspectRatio();
    }
  }

}

exports.default = Video;

/***/ }),

/***/ "../assets/dev/js/frontend/preloaded-modules.js":
/*!******************************************************!*\
  !*** ../assets/dev/js/frontend/preloaded-modules.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __unused_webpack_exports, __webpack_require__) => {

"use strict";


var _interopRequireDefault = __webpack_require__(/*! @babel/runtime/helpers/interopRequireDefault */ "../node_modules/@babel/runtime/helpers/interopRequireDefault.js");

var _accordion = _interopRequireDefault(__webpack_require__(/*! ./handlers/accordion */ "../assets/dev/js/frontend/handlers/accordion.js"));

var _alert = _interopRequireDefault(__webpack_require__(/*! ./handlers/alert */ "../assets/dev/js/frontend/handlers/alert.js"));

var _counter = _interopRequireDefault(__webpack_require__(/*! ./handlers/counter */ "../assets/dev/js/frontend/handlers/counter.js"));

var _progress = _interopRequireDefault(__webpack_require__(/*! ./handlers/progress */ "../assets/dev/js/frontend/handlers/progress.js"));

var _tabs = _interopRequireDefault(__webpack_require__(/*! ./handlers/tabs */ "../assets/dev/js/frontend/handlers/tabs.js"));

var _toggle = _interopRequireDefault(__webpack_require__(/*! ./handlers/toggle */ "../assets/dev/js/frontend/handlers/toggle.js"));

var _video = _interopRequireDefault(__webpack_require__(/*! ./handlers/video */ "../assets/dev/js/frontend/handlers/video.js"));

var _imageCarousel = _interopRequireDefault(__webpack_require__(/*! ./handlers/image-carousel */ "../assets/dev/js/frontend/handlers/image-carousel.js"));

var _textEditor = _interopRequireDefault(__webpack_require__(/*! ./handlers/text-editor */ "../assets/dev/js/frontend/handlers/text-editor.js"));

var _lightbox = _interopRequireDefault(__webpack_require__(/*! elementor-frontend/utils/lightbox/lightbox */ "../assets/dev/js/frontend/utils/lightbox/lightbox.js"));

elementorFrontend.elements.$window.on('elementor/frontend/init', () => {
  elementorFrontend.elementsHandler.elementsHandlers = {
    'accordion.default': _accordion.default,
    'alert.default': _alert.default,
    'counter.default': _counter.default,
    'progress.default': _progress.default,
    'tabs.default': _tabs.default,
    'toggle.default': _toggle.default,
    'video.default': _video.default,
    'image-carousel.default': _imageCarousel.default,
    'text-editor.default': _textEditor.default
  };
  elementorFrontend.on('components:init', () => {
    // We first need to delete the property because by default it's a getter function that cannot be overwritten.
    delete elementorFrontend.utils.lightbox;
    elementorFrontend.utils.lightbox = new _lightbox.default();
  });
});

/***/ }),

/***/ "../assets/dev/js/frontend/utils/lightbox/lightbox.js":
/*!************************************************************!*\
  !*** ../assets/dev/js/frontend/utils/lightbox/lightbox.js ***!
  \************************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

"use strict";


var _interopRequireDefault = __webpack_require__(/*! @babel/runtime/helpers/interopRequireDefault */ "../node_modules/@babel/runtime/helpers/interopRequireDefault.js");

var _screenfull = _interopRequireDefault(__webpack_require__(/*! ./screenfull */ "../assets/dev/js/frontend/utils/lightbox/screenfull.js"));

module.exports = elementorModules.ViewModule.extend({
  oldAspectRatio: null,
  oldAnimation: null,
  swiper: null,
  player: null,
  getDefaultSettings: function () {
    return {
      classes: {
        aspectRatio: 'elementor-aspect-ratio-%s',
        item: 'elementor-lightbox-item',
        image: 'elementor-lightbox-image',
        videoContainer: 'elementor-video-container',
        videoWrapper: 'elementor-fit-aspect-ratio',
        playButton: 'elementor-custom-embed-play',
        playButtonIcon: 'fa',
        playing: 'elementor-playing',
        hidden: 'elementor-hidden',
        invisible: 'elementor-invisible',
        preventClose: 'elementor-lightbox-prevent-close',
        slideshow: {
          container: 'swiper-container',
          slidesWrapper: 'swiper-wrapper',
          prevButton: 'elementor-swiper-button elementor-swiper-button-prev',
          nextButton: 'elementor-swiper-button elementor-swiper-button-next',
          prevButtonIcon: 'eicon-chevron-left',
          nextButtonIcon: 'eicon-chevron-right',
          slide: 'swiper-slide',
          header: 'elementor-slideshow__header',
          footer: 'elementor-slideshow__footer',
          title: 'elementor-slideshow__title',
          description: 'elementor-slideshow__description',
          counter: 'elementor-slideshow__counter',
          iconExpand: 'eicon-frame-expand',
          iconShrink: 'eicon-frame-minimize',
          iconZoomIn: 'eicon-zoom-in-bold',
          iconZoomOut: 'eicon-zoom-out-bold',
          iconShare: 'eicon-share-arrow',
          shareMenu: 'elementor-slideshow__share-menu',
          shareLinks: 'elementor-slideshow__share-links',
          hideUiVisibility: 'elementor-slideshow--ui-hidden',
          shareMode: 'elementor-slideshow--share-mode',
          fullscreenMode: 'elementor-slideshow--fullscreen-mode',
          zoomMode: 'elementor-slideshow--zoom-mode'
        }
      },
      selectors: {
        image: '.elementor-lightbox-image',
        links: 'a, [data-elementor-lightbox]',
        slideshow: {
          activeSlide: '.swiper-slide-active',
          prevSlide: '.swiper-slide-prev',
          nextSlide: '.swiper-slide-next'
        }
      },
      modalOptions: {
        id: 'elementor-lightbox',
        entranceAnimation: 'zoomIn',
        videoAspectRatio: 169,
        position: {
          enable: false
        }
      }
    };
  },
  getModal: function () {
    if (!module.exports.modal) {
      this.initModal();
    }

    return module.exports.modal;
  },
  initModal: function () {
    const modal = module.exports.modal = elementorFrontend.getDialogsManager().createWidget('lightbox', {
      className: 'elementor-lightbox',
      closeButton: true,
      closeButtonOptions: {
        iconClass: 'eicon-close',
        attributes: {
          tabindex: 0,
          role: 'button',
          'aria-label': elementorFrontend.config.i18n.close + ' (Esc)'
        }
      },
      selectors: {
        preventClose: '.' + this.getSettings('classes.preventClose')
      },
      hide: {
        onClick: true
      }
    });
    modal.on('hide', function () {
      modal.setMessage('');
    });
  },
  showModal: function (options) {
    if (options.url && !options.url.startsWith('http')) {
      return;
    }

    this.elements.$closeButton = this.getModal().getElements('closeButton');
    this.$buttons = this.elements.$closeButton;
    this.focusedButton = null;
    const self = this,
          defaultOptions = self.getDefaultSettings().modalOptions;
    self.id = options.id;
    self.setSettings('modalOptions', jQuery.extend(defaultOptions, options.modalOptions));
    const modal = self.getModal();
    modal.setID(self.getSettings('modalOptions.id'));

    modal.onShow = function () {
      DialogsManager.getWidgetType('lightbox').prototype.onShow.apply(modal, arguments);
      self.setEntranceAnimation();
    };

    modal.onHide = function () {
      DialogsManager.getWidgetType('lightbox').prototype.onHide.apply(modal, arguments);
      modal.getElements('message').removeClass('animated');

      if (_screenfull.default.isFullscreen) {
        self.deactivateFullscreen();
      }

      self.unbindHotKeys();
    };

    switch (options.type) {
      case 'video':
        self.setVideoContent(options);
        break;

      case 'image':
        const slides = [{
          image: options.url,
          index: 0,
          title: options.title,
          description: options.description
        }];
        options.slideshow = {
          slides,
          swiper: {
            loop: false,
            pagination: false
          }
        };

      case 'slideshow':
        self.setSlideshowContent(options.slideshow);
        break;

      default:
        self.setHTMLContent(options.html);
    }

    modal.show();
  },
  createLightbox: function (element) {
    let lightboxData = {};

    if (element.dataset.elementorLightbox) {
      lightboxData = JSON.parse(element.dataset.elementorLightbox);
    }

    if (lightboxData.type && 'slideshow' !== lightboxData.type) {
      this.showModal(lightboxData);
      return;
    }

    if (!element.dataset.elementorLightboxSlideshow) {
      const slideshowID = 'single-img';
      this.showModal({
        type: 'image',
        id: slideshowID,
        url: element.href,
        title: element.dataset.elementorLightboxTitle,
        description: element.dataset.elementorLightboxDescription,
        modalOptions: {
          id: 'elementor-lightbox-slideshow-' + slideshowID
        }
      });
      return;
    }

    const initialSlideURL = element.dataset.elementorLightboxVideo || element.href;
    this.openSlideshow(element.dataset.elementorLightboxSlideshow, initialSlideURL);
  },
  setHTMLContent: function (html) {
    if (window.elementorCommon) {
      elementorCommon.helpers.hardDeprecated('elementorFrontend.utils.lightbox.setHTMLContent', '3.1.4');
    }

    this.getModal().setMessage(html);
  },
  setVideoContent: function (options) {
    const $ = jQuery,
          classes = this.getSettings('classes'),
          $videoContainer = $('<div>', {
      class: `${classes.videoContainer} ${classes.preventClose}`
    }),
          $videoWrapper = $('<div>', {
      class: classes.videoWrapper
    }),
          modal = this.getModal();
    let $videoElement;

    if ('hosted' === options.videoType) {
      const videoParams = $.extend({
        src: options.url,
        autoplay: ''
      }, options.videoParams);
      $videoElement = $('<video>', videoParams);
    } else {
      let apiProvider = elementorFrontend.utils.baseVideoLoader;

      if (-1 !== options.url.indexOf('vimeo.com')) {
        apiProvider = elementorFrontend.utils.vimeo;
      } else if (options.url.match(/^(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com)/)) {
        apiProvider = elementorFrontend.utils.youtube;
      }

      $videoElement = $('<iframe>', {
        src: apiProvider.getAutoplayURL(options.url),
        allowfullscreen: 1
      });
    }

    $videoContainer.append($videoWrapper);
    $videoWrapper.append($videoElement);
    modal.setMessage($videoContainer);
    this.setVideoAspectRatio();
    const onHideMethod = modal.onHide;

    modal.onHide = function () {
      onHideMethod();
      this.$buttons = jQuery();
      this.focusedButton = null;
      modal.getElements('message').removeClass('elementor-fit-aspect-ratio');
    };
  },
  getShareLinks: function () {
    const {
      i18n
    } = elementorFrontend.config,
          socialNetworks = {
      facebook: i18n.shareOnFacebook,
      twitter: i18n.shareOnTwitter,
      pinterest: i18n.pinIt
    },
          $ = jQuery,
          classes = this.getSettings('classes'),
          selectors = this.getSettings('selectors'),
          $linkList = $('<div>', {
      class: classes.slideshow.shareLinks
    }),
          $activeSlide = this.getSlide('active'),
          $image = $activeSlide.find(selectors.image),
          videoUrl = $activeSlide.data('elementor-slideshow-video');
    let itemUrl;

    if (videoUrl) {
      itemUrl = videoUrl;
    } else {
      itemUrl = $image.attr('src');
    }

    $.each(socialNetworks, (key, networkLabel) => {
      const $link = $('<a>', {
        href: this.createShareLink(key, itemUrl),
        target: '_blank'
      }).text(networkLabel);
      $link.prepend($('<i>', {
        class: 'eicon-' + key
      }));
      $linkList.append($link);
    });

    if (!videoUrl) {
      $linkList.append($('<a>', {
        href: itemUrl,
        download: ''
      }).text(i18n.downloadImage).prepend($('<i>', {
        class: 'eicon-download-bold',
        'aria-label': i18n.download
      })));
    }

    return $linkList;
  },
  createShareLink: function (networkName, itemUrl) {
    const options = {};

    if ('pinterest' === networkName) {
      options.image = encodeURIComponent(itemUrl);
    } else {
      const hash = elementorFrontend.utils.urlActions.createActionHash('lightbox', {
        id: this.id,
        url: itemUrl
      });
      options.url = encodeURIComponent(location.href.replace(/#.*/, '')) + hash;
    }

    return ShareLink.getNetworkLink(networkName, options);
  },
  getSlideshowHeader: function () {
    const {
      i18n
    } = elementorFrontend.config,
          $ = jQuery,
          showCounter = 'yes' === elementorFrontend.getKitSettings('lightbox_enable_counter'),
          showFullscreen = 'yes' === elementorFrontend.getKitSettings('lightbox_enable_fullscreen'),
          showZoom = 'yes' === elementorFrontend.getKitSettings('lightbox_enable_zoom'),
          showShare = 'yes' === elementorFrontend.getKitSettings('lightbox_enable_share'),
          classes = this.getSettings('classes'),
          slideshowClasses = classes.slideshow,
          elements = this.elements;

    if (!(showCounter || showFullscreen || showZoom || showShare)) {
      return;
    }

    elements.$header = $('<header>', {
      class: slideshowClasses.header + ' ' + classes.preventClose
    });

    if (showShare) {
      elements.$iconShare = $('<i>', {
        class: slideshowClasses.iconShare,
        role: 'button',
        'aria-label': i18n.share,
        'aria-expanded': false
      }).append($('<span>'));
      const $shareLinks = $('<div>');
      $shareLinks.on('click', e => {
        e.stopPropagation();
      });
      elements.$shareMenu = $('<div>', {
        class: slideshowClasses.shareMenu
      }).append($shareLinks);
      elements.$iconShare.add(elements.$shareMenu).on('click', this.toggleShareMenu);
      elements.$header.append(elements.$iconShare, elements.$shareMenu);
      this.$buttons = this.$buttons.add(elements.$iconShare);
    }

    if (showZoom) {
      elements.$iconZoom = $('<i>', {
        class: slideshowClasses.iconZoomIn,
        role: 'switch',
        'aria-checked': false,
        'aria-label': i18n.zoom
      });
      elements.$iconZoom.on('click', this.toggleZoomMode);
      elements.$header.append(elements.$iconZoom);
      this.$buttons = this.$buttons.add(elements.$iconZoom);
    }

    if (showFullscreen) {
      elements.$iconExpand = $('<i>', {
        class: slideshowClasses.iconExpand,
        role: 'switch',
        'aria-checked': false,
        'aria-label': i18n.fullscreen
      }).append($('<span>'), $('<span>'));
      elements.$iconExpand.on('click', this.toggleFullscreen);
      elements.$header.append(elements.$iconExpand);
      this.$buttons = this.$buttons.add(elements.$iconExpand);
    }

    if (showCounter) {
      elements.$counter = $('<span>', {
        class: slideshowClasses.counter
      });
      elements.$header.append(elements.$counter);
    }

    return elements.$header;
  },
  toggleFullscreen: function () {
    if (_screenfull.default.isFullscreen) {
      this.deactivateFullscreen();
    } else if (_screenfull.default.isEnabled) {
      this.activateFullscreen();
    }
  },
  toggleZoomMode: function () {
    if (1 !== this.swiper.zoom.scale) {
      this.deactivateZoom();
    } else {
      this.activateZoom();
    }
  },
  toggleShareMenu: function () {
    if (this.shareMode) {
      this.deactivateShareMode();
    } else {
      this.elements.$shareMenu.html(this.getShareLinks());
      this.activateShareMode();
    }
  },
  activateShareMode: function () {
    const classes = this.getSettings('classes');
    this.elements.$container.addClass(classes.slideshow.shareMode);
    this.elements.$iconShare.attr('aria-expanded', true); // Prevent swiper interactions while in share mode

    this.swiper.detachEvents(); // Temporarily replace tabbable buttons with share-menu items

    this.$originalButtons = this.$buttons;
    this.$buttons = this.elements.$iconShare.add(this.elements.$shareMenu.find('a'));
    this.shareMode = true;
  },
  deactivateShareMode: function () {
    const classes = this.getSettings('classes');
    this.elements.$container.removeClass(classes.slideshow.shareMode);
    this.elements.$iconShare.attr('aria-expanded', false);
    this.swiper.attachEvents();
    this.$buttons = this.$originalButtons;
    this.shareMode = false;
  },
  activateFullscreen: function () {
    const classes = this.getSettings('classes');

    _screenfull.default.request(this.elements.$container.parents('.dialog-widget')[0]);

    this.elements.$iconExpand.removeClass(classes.slideshow.iconExpand).addClass(classes.slideshow.iconShrink).attr('aria-checked', 'true');
    this.elements.$container.addClass(classes.slideshow.fullscreenMode);
  },
  deactivateFullscreen: function () {
    const classes = this.getSettings('classes');

    _screenfull.default.exit();

    this.elements.$iconExpand.removeClass(classes.slideshow.iconShrink).addClass(classes.slideshow.iconExpand).attr('aria-checked', 'false');
    this.elements.$container.removeClass(classes.slideshow.fullscreenMode);
  },
  activateZoom: function () {
    const swiper = this.swiper,
          elements = this.elements,
          classes = this.getSettings('classes');
    swiper.zoom.in();
    swiper.allowSlideNext = false;
    swiper.allowSlidePrev = false;
    swiper.allowTouchMove = false;
    elements.$container.addClass(classes.slideshow.zoomMode);
    elements.$iconZoom.removeClass(classes.slideshow.iconZoomIn).addClass(classes.slideshow.iconZoomOut);
  },
  deactivateZoom: function () {
    const swiper = this.swiper,
          elements = this.elements,
          classes = this.getSettings('classes');
    swiper.zoom.out();
    swiper.allowSlideNext = true;
    swiper.allowSlidePrev = true;
    swiper.allowTouchMove = true;
    elements.$container.removeClass(classes.slideshow.zoomMode);
    elements.$iconZoom.removeClass(classes.slideshow.iconZoomOut).addClass(classes.slideshow.iconZoomIn);
  },
  getSlideshowFooter: function () {
    const $ = jQuery,
          classes = this.getSettings('classes'),
          $footer = $('<footer>', {
      class: classes.slideshow.footer + ' ' + classes.preventClose
    }),
          $title = $('<div>', {
      class: classes.slideshow.title
    }),
          $description = $('<div>', {
      class: classes.slideshow.description
    });
    $footer.append($title, $description);
    return $footer;
  },
  setSlideshowContent: function (options) {
    const {
      i18n
    } = elementorFrontend.config,
          $ = jQuery,
          isSingleSlide = 1 === options.slides.length,
          hasTitle = '' !== elementorFrontend.getKitSettings('lightbox_title_src'),
          hasDescription = '' !== elementorFrontend.getKitSettings('lightbox_description_src'),
          showFooter = hasTitle || hasDescription,
          classes = this.getSettings('classes'),
          slideshowClasses = classes.slideshow,
          $container = $('<div>', {
      class: slideshowClasses.container
    }),
          $slidesWrapper = $('<div>', {
      class: slideshowClasses.slidesWrapper
    });
    let $prevButton, $nextButton;
    options.slides.forEach(slide => {
      let slideClass = slideshowClasses.slide + ' ' + classes.item;

      if (slide.video) {
        slideClass += ' ' + classes.video;
      }

      const $slide = $('<div>', {
        class: slideClass
      });

      if (slide.video) {
        $slide.attr('data-elementor-slideshow-video', slide.video);
        const $playIcon = $('<div>', {
          class: classes.playButton
        }).html($('<i>', {
          class: classes.playButtonIcon,
          'aria-label': i18n.playVideo
        }));
        $slide.append($playIcon);
      } else {
        const $zoomContainer = $('<div>', {
          class: 'swiper-zoom-container'
        }),
              $slidePlaceholder = $('<div class="swiper-lazy-preloader"></div>'),
              imageAttributes = {
          'data-src': slide.image,
          class: classes.image + ' ' + classes.preventClose + ' swiper-lazy'
        };

        if (slide.title) {
          imageAttributes['data-title'] = slide.title;
          imageAttributes.alt = slide.title;
        }

        if (slide.description) {
          imageAttributes['data-description'] = slide.description;
          imageAttributes.alt += ' - ' + slide.description;
        }

        const $slideImage = $('<img>', imageAttributes);
        $zoomContainer.append([$slideImage, $slidePlaceholder]);
        $slide.append($zoomContainer);
      }

      $slidesWrapper.append($slide);
    });
    this.elements.$container = $container;
    this.elements.$header = this.getSlideshowHeader();
    $container.prepend(this.elements.$header).append($slidesWrapper);

    if (!isSingleSlide) {
      $prevButton = $('<div>', {
        class: slideshowClasses.prevButton + ' ' + classes.preventClose,
        'aria-label': i18n.previous
      }).html($('<i>', {
        class: slideshowClasses.prevButtonIcon
      }));
      $nextButton = $('<div>', {
        class: slideshowClasses.nextButton + ' ' + classes.preventClose,
        'aria-label': i18n.next
      }).html($('<i>', {
        class: slideshowClasses.nextButtonIcon
      }));
      $container.append($nextButton, $prevButton);
      this.$buttons = this.$buttons.add($nextButton).add($prevButton);
    }

    if (showFooter) {
      this.elements.$footer = this.getSlideshowFooter();
      $container.append(this.elements.$footer);
    }

    this.setSettings('hideUiTimeout', '');
    $container.on('click mousemove keypress', this.showLightboxUi);
    const modal = this.getModal();
    modal.setMessage($container);
    const onShowMethod = modal.onShow;

    modal.onShow = async () => {
      onShowMethod();
      const swiperOptions = {
        pagination: {
          el: '.' + slideshowClasses.counter,
          type: 'fraction'
        },
        on: {
          slideChangeTransitionEnd: this.onSlideChange
        },
        lazy: {
          loadPrevNext: true
        },
        zoom: true,
        spaceBetween: 100,
        grabCursor: true,
        runCallbacksOnInit: false,
        loop: true,
        keyboard: true,
        handleElementorBreakpoints: true
      };

      if (!isSingleSlide) {
        swiperOptions.navigation = {
          prevEl: $prevButton,
          nextEl: $nextButton
        };
      }

      if (options.swiper) {
        $.extend(swiperOptions, options.swiper);
      }

      const Swiper = elementorFrontend.utils.swiper;
      this.swiper = await new Swiper($container, swiperOptions); // Expose the swiper instance in the frontend

      $container.data('swiper', this.swiper);
      this.setVideoAspectRatio();
      this.playSlideVideo();

      if (showFooter) {
        this.updateFooterText();
      }

      this.bindHotKeys();
      this.makeButtonsAccessible();
    };
  },
  makeButtonsAccessible: function () {
    this.$buttons.attr('tabindex', 0).on('keypress', event => {
      const ENTER_KEY = 13,
            SPACE_KEY = 32;

      if (ENTER_KEY === event.which || SPACE_KEY === event.which) {
        jQuery(event.currentTarget).trigger('click');
      }
    });
  },
  showLightboxUi: function () {
    const slideshowClasses = this.getSettings('classes').slideshow;
    this.elements.$container.removeClass(slideshowClasses.hideUiVisibility);
    clearTimeout(this.getSettings('hideUiTimeout'));
    this.setSettings('hideUiTimeout', setTimeout(() => {
      if (!this.shareMode) {
        this.elements.$container.addClass(slideshowClasses.hideUiVisibility);
      }
    }, 3500));
  },
  bindHotKeys: function () {
    this.getModal().getElements('window').on('keydown', this.activeKeyDown);
  },
  unbindHotKeys: function () {
    this.getModal().getElements('window').off('keydown', this.activeKeyDown);
  },
  activeKeyDown: function (event) {
    this.showLightboxUi();
    const TAB_KEY = 9;

    if (event.which === TAB_KEY) {
      const $buttons = this.$buttons;
      let focusedButton,
          isFirst = false,
          isLast = false;
      $buttons.each(index => {
        const item = $buttons[index];

        if (jQuery(item).is(':focus')) {
          focusedButton = item;
          isFirst = 0 === index;
          isLast = $buttons.length - 1 === index;
          return false;
        }
      });

      if (event.shiftKey) {
        if (isFirst) {
          event.preventDefault();
          $buttons.last().trigger('focus');
        }
      } else if (isLast || !focusedButton) {
        event.preventDefault();
        $buttons.first().trigger('focus');
      }
    }
  },
  setVideoAspectRatio: function (aspectRatio) {
    aspectRatio = aspectRatio || this.getSettings('modalOptions.videoAspectRatio');
    const $widgetContent = this.getModal().getElements('widgetContent'),
          oldAspectRatio = this.oldAspectRatio,
          aspectRatioClass = this.getSettings('classes.aspectRatio');
    this.oldAspectRatio = aspectRatio;

    if (oldAspectRatio) {
      $widgetContent.removeClass(aspectRatioClass.replace('%s', oldAspectRatio));
    }

    if (aspectRatio) {
      $widgetContent.addClass(aspectRatioClass.replace('%s', aspectRatio));
    }
  },
  getSlide: function (slideState) {
    return jQuery(this.swiper.slides).filter(this.getSettings('selectors.slideshow.' + slideState + 'Slide'));
  },
  updateFooterText: function () {
    if (!this.elements.$footer) {
      return;
    }

    const classes = this.getSettings('classes'),
          $activeSlide = this.getSlide('active'),
          $image = $activeSlide.find('.elementor-lightbox-image'),
          titleText = $image.data('title'),
          descriptionText = $image.data('description'),
          $title = this.elements.$footer.find('.' + classes.slideshow.title),
          $description = this.elements.$footer.find('.' + classes.slideshow.description);
    $title.text(titleText || '');
    $description.text(descriptionText || '');
  },
  playSlideVideo: function () {
    const $activeSlide = this.getSlide('active'),
          videoURL = $activeSlide.data('elementor-slideshow-video');

    if (!videoURL) {
      return;
    }

    const classes = this.getSettings('classes'),
          $videoContainer = jQuery('<div>', {
      class: classes.videoContainer + ' ' + classes.invisible
    }),
          $videoWrapper = jQuery('<div>', {
      class: classes.videoWrapper
    }),
          $playIcon = $activeSlide.children('.' + classes.playButton);
    let videoType, apiProvider;
    $videoContainer.append($videoWrapper);
    $activeSlide.append($videoContainer);

    if (-1 !== videoURL.indexOf('vimeo.com')) {
      videoType = 'vimeo';
      apiProvider = elementorFrontend.utils.vimeo;
    } else if (videoURL.match(/^(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com)/)) {
      videoType = 'youtube';
      apiProvider = elementorFrontend.utils.youtube;
    }

    const videoID = apiProvider.getVideoIDFromURL(videoURL);
    apiProvider.onApiReady(apiObject => {
      if ('youtube' === videoType) {
        this.prepareYTVideo(apiObject, videoID, $videoContainer, $videoWrapper, $playIcon);
      } else if ('vimeo' === videoType) {
        this.prepareVimeoVideo(apiObject, videoID, $videoContainer, $videoWrapper, $playIcon);
      }
    });
    $playIcon.addClass(classes.playing).removeClass(classes.hidden);
  },
  prepareYTVideo: function (YT, videoID, $videoContainer, $videoWrapper, $playIcon) {
    const classes = this.getSettings('classes'),
          $videoPlaceholderElement = jQuery('<div>');
    let startStateCode = YT.PlayerState.PLAYING;
    $videoWrapper.append($videoPlaceholderElement); // Since version 67, Chrome doesn't fire the `PLAYING` state at start time

    if (window.chrome) {
      startStateCode = YT.PlayerState.UNSTARTED;
    }

    $videoContainer.addClass('elementor-loading' + ' ' + classes.invisible);
    this.player = new YT.Player($videoPlaceholderElement[0], {
      videoId: videoID,
      events: {
        onReady: () => {
          $playIcon.addClass(classes.hidden);
          $videoContainer.removeClass(classes.invisible);
          this.player.playVideo();
        },
        onStateChange: event => {
          if (event.data === startStateCode) {
            $videoContainer.removeClass('elementor-loading' + ' ' + classes.invisible);
          }
        }
      },
      playerVars: {
        controls: 0,
        rel: 0
      }
    });
  },
  prepareVimeoVideo: function (Vimeo, videoId, $videoContainer, $videoWrapper, $playIcon) {
    const classes = this.getSettings('classes'),
          vimeoOptions = {
      id: videoId,
      autoplay: true,
      transparent: false,
      playsinline: false
    };
    this.player = new Vimeo.Player($videoWrapper, vimeoOptions);
    this.player.ready().then(() => {
      $playIcon.addClass(classes.hidden);
      $videoContainer.removeClass(classes.invisible);
    });
  },
  setEntranceAnimation: function (animation) {
    animation = animation || elementorFrontend.getCurrentDeviceSetting(this.getSettings('modalOptions'), 'entranceAnimation');
    const $widgetMessage = this.getModal().getElements('message');

    if (this.oldAnimation) {
      $widgetMessage.removeClass(this.oldAnimation);
    }

    this.oldAnimation = animation;

    if (animation) {
      $widgetMessage.addClass('animated ' + animation);
    }
  },
  openSlideshow: function (slideshowID, initialSlideURL) {
    const $allSlideshowLinks = jQuery(this.getSettings('selectors.links')).filter((index, element) => {
      const $element = jQuery(element);
      return slideshowID === element.dataset.elementorLightboxSlideshow && !$element.parent('.swiper-slide-duplicate').length && !$element.parents('.slick-cloned').length;
    });
    const slides = [];
    let initialSlideIndex = 0;
    $allSlideshowLinks.each(function () {
      const slideVideo = this.dataset.elementorLightboxVideo;
      let slideIndex = this.dataset.elementorLightboxIndex;

      if (undefined === slideIndex) {
        slideIndex = $allSlideshowLinks.index(this);
      }

      if (initialSlideURL === this.href || slideVideo && initialSlideURL === slideVideo) {
        initialSlideIndex = slideIndex;
      }

      const slideData = {
        image: this.href,
        index: slideIndex,
        title: this.dataset.elementorLightboxTitle,
        description: this.dataset.elementorLightboxDescription
      };

      if (slideVideo) {
        slideData.video = slideVideo;
      }

      slides.push(slideData);
    });
    slides.sort((a, b) => a.index - b.index);
    this.showModal({
      type: 'slideshow',
      id: slideshowID,
      modalOptions: {
        id: 'elementor-lightbox-slideshow-' + slideshowID
      },
      slideshow: {
        slides: slides,
        swiper: {
          initialSlide: +initialSlideIndex
        }
      }
    });
  },
  onSlideChange: function () {
    this.getSlide('prev').add(this.getSlide('next')).add(this.getSlide('active')).find('.' + this.getSettings('classes.videoWrapper')).remove();
    this.playSlideVideo();
    this.updateFooterText();
  }
});

/***/ }),

/***/ "../assets/dev/js/frontend/utils/lightbox/screenfull.js":
/*!**************************************************************!*\
  !*** ../assets/dev/js/frontend/utils/lightbox/screenfull.js ***!
  \**************************************************************/
/***/ ((module) => {

"use strict";


(function () {
  'use strict';

  var document = typeof window !== 'undefined' && typeof window.document !== 'undefined' ? window.document : {};
  var isCommonjs =  true && module.exports;

  var fn = function () {
    var val;
    var fnMap = [['requestFullscreen', 'exitFullscreen', 'fullscreenElement', 'fullscreenEnabled', 'fullscreenchange', 'fullscreenerror'], // New WebKit
    ['webkitRequestFullscreen', 'webkitExitFullscreen', 'webkitFullscreenElement', 'webkitFullscreenEnabled', 'webkitfullscreenchange', 'webkitfullscreenerror'], // Old WebKit
    ['webkitRequestFullScreen', 'webkitCancelFullScreen', 'webkitCurrentFullScreenElement', 'webkitCancelFullScreen', 'webkitfullscreenchange', 'webkitfullscreenerror'], ['mozRequestFullScreen', 'mozCancelFullScreen', 'mozFullScreenElement', 'mozFullScreenEnabled', 'mozfullscreenchange', 'mozfullscreenerror'], ['msRequestFullscreen', 'msExitFullscreen', 'msFullscreenElement', 'msFullscreenEnabled', 'MSFullscreenChange', 'MSFullscreenError']];
    var i = 0;
    var l = fnMap.length;
    var ret = {};

    for (; i < l; i++) {
      val = fnMap[i];

      if (val && val[1] in document) {
        var valLength = val.length;

        for (i = 0; i < valLength; i++) {
          ret[fnMap[0][i]] = val[i];
        }

        return ret;
      }
    }

    return false;
  }();

  var eventNameMap = {
    change: fn.fullscreenchange,
    error: fn.fullscreenerror
  };
  var screenfull = {
    request: function (element) {
      return new Promise(function (resolve, reject) {
        var onFullScreenEntered = function () {
          this.off('change', onFullScreenEntered);
          resolve();
        }.bind(this);

        this.on('change', onFullScreenEntered);
        element = element || document.documentElement;
        Promise.resolve(element[fn.requestFullscreen]()).catch(reject);
      }.bind(this));
    },
    exit: function () {
      return new Promise(function (resolve, reject) {
        if (!this.isFullscreen) {
          resolve();
          return;
        }

        var onFullScreenExit = function () {
          this.off('change', onFullScreenExit);
          resolve();
        }.bind(this);

        this.on('change', onFullScreenExit);
        Promise.resolve(document[fn.exitFullscreen]()).catch(reject);
      }.bind(this));
    },
    toggle: function (element) {
      return this.isFullscreen ? this.exit() : this.request(element);
    },
    onchange: function (callback) {
      this.on('change', callback);
    },
    onerror: function (callback) {
      this.on('error', callback);
    },
    on: function (event, callback) {
      var eventName = eventNameMap[event];

      if (eventName) {
        document.addEventListener(eventName, callback, false);
      }
    },
    off: function (event, callback) {
      var eventName = eventNameMap[event];

      if (eventName) {
        document.removeEventListener(eventName, callback, false);
      }
    },
    raw: fn
  };

  if (!fn) {
    if (isCommonjs) {
      module.exports = {
        isEnabled: false
      };
    } else {
      window.screenfull = {
        isEnabled: false
      };
    }

    return;
  }

  Object.defineProperties(screenfull, {
    isFullscreen: {
      get: function () {
        return Boolean(document[fn.fullscreenElement]);
      }
    },
    element: {
      enumerable: true,
      get: function () {
        return document[fn.fullscreenElement];
      }
    },
    isEnabled: {
      enumerable: true,
      get: function () {
        // Coerce to boolean in case of old WebKit
        return Boolean(document[fn.fullscreenEnabled]);
      }
    }
  });

  if (isCommonjs) {
    module.exports = screenfull;
  } else {
    window.screenfull = screenfull;
  }
})();

/***/ })

},
/******/ __webpack_require__ => { // webpackRuntimeModules
/******/ "use strict";
/******/ 
/******/ var __webpack_exec__ = (moduleId) => (__webpack_require__(__webpack_require__.s = moduleId))
/******/ __webpack_require__.O(0, ["frontend","frontend-modules"], () => (__webpack_exec__("../assets/dev/js/frontend/preloaded-modules.js")));
/******/ var __webpack_exports__ = __webpack_require__.O();
/******/ }
]);
//# sourceMappingURL=preloaded-modules.js.map