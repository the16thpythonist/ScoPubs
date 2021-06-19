import Vue from 'vue';
import Options from './components/Options';
import AuthorMeta from './components/AuthorMeta';
import PublicationMeta from "./components/PublicationMeta";
import LogMeta from "./components/LogMeta";
import CommandWidget from "./components/CommandWidget";

Vue.config.productionTip = true;

// THE MOTIVATION
// Using Vue to supply the dynamic JS interactions for a wordpress installation, we dont need a single page application
// Instead we need multiple components which would only be loaded on different pages of the wordpress backend. An
// example would be to use one component to create a rich and interactive options page and another component as the
// meta box for a custom post type. This makes it necessary to only conditionally load these components whenever they
// are needed on a given page.

// THE IDEA
// At the center of it is this "components" dict, which defines key value pairs: The key is a html ID for which to
// substitute the Vue component set as the corresponding value. At the load of each page, we check if an element with
// such an ID exists and if it does we mount the corresponding Vue component. Otherwise nothing will happen.
let components = {
  'scopubs-options-component': Options,
  'scopubs-author-meta-component': AuthorMeta,
  'scopubs-publication-meta-component': PublicationMeta,
  'scopubs-log-meta-component': LogMeta,
  'command-widget-component': CommandWidget,
}

function attachVue() {

  // It is important, that this code is executed inside the window.onload callback.
  // Previously it was on the top level and thus executed as soon as the script was loaded. But at that time not all
  // DOM elements necessarily exists, which means that sometimes there would be a bug that caused the vue component not
  // to be mounted because this code was executed before the element with the corresponding ID even existed in the DOM!
  for (let [id, component] of Object.entries(components)) {
    let element = document.getElementById(id);
    if (element) {
      // If the element exists, we can use it to mount the corresponding Vue component dynamically.
      new Vue({
        render: h => h(component)
      }).$mount('#' + id);
    }
  }
}

window.addEventListener('load', attachVue);