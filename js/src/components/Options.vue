<template>
    <div class="options">
        <div class="wrap">
            <h1>{{ WP.plugin_name }} Options</h1>
            <!--
            This entire following section is structured like any other default wordpress options page. All the elements
            have the same classes and the same order, such that the wordpress styling rules are being applied to them
            and the widget looks entirely like any other options page.
            The only difference is the div with the "option" class. This is not usually found in a wordpress options
            page but in this case we need it to apply the v-for directive. In any way, it does not break the styling.
            -->
            <table class="form-table">
                <tbody>
                    <div
                            class="option"
                            v-for="([optionName, optionData], index) in Object.entries(this.options)">
                        <th scope="row">
                            <label :for="optionName">{{ optionData['label'] }}</label>
                        </th>
                        <td>
                            <input
                                    :id="optionName"
                                    type="text"
                                    v-model="optionData['value']">
                            <p class="description">{{ optionData['description'] }}</p>
                        </td>
                    </div>
                </tbody>
            </table>

            <!--
            At this point this options widget also differs from the default wordpress options page. We dont actually
            trigger a form submit with this button. Instead the callback to this button will save the values via the
            REST API and then reload the page. This is actually just easier to do.
            -->
            <p class="submit">
                <button
                        class="button button-primary"
                        @click.prevent="onSave">
                    Save Changes
                </button>
            </p>
        </div>
    </div>
</template>

<script>
    /**
     * **BASIC IDEA**
     *
     * This widget implements the actual frontend interface for setting all the options values for this plugin. This
     * widget is special in the sense that it dynamically generates all the input fields based on which options are
     * registered in the backend. This is huge convenience feature! When adding a new option to the plugin, the actual
     * options page usually doesn't have to be touched at all (Except for some really custom behavior).
     *
     * The only thing one has to do is to add a new entry in the Options::$options static array in the PHP code. With
     * this new entry, the option will also be automatically be exposed to the REST api for interacting with the
     * options. This frontend widget then fetches this information about all available options and then dynamically
     * creates a new input field for each option. Even better is that it even creates different appropriate input
     * methods depending on the specified type of the option.
     */
    import api from '../api.js';

    export default {
        name: "Options",
        data: function() {
            return {
                WP: WP,
                api: new api.Api(),
                options: {}
            }
        },
        methods: {
            /**
             * Callback for pressing the "save changes" button. This will send a POST request to the options REST route
             * to update all the database values for the options and then after a short delay reloads the page.
             *
             * @return void
             */
            onSave: function() {
                this.api.updateOptions(this.optionValues);
                // After a short delay we reload the page, so the user can be sure that the options were actually
                // changed.
                setTimeout(function() {
                    window.location.reload();
                }, 500);
            },
        },
        computed: {
            /**
             * The this.options object consists of key value pairs where the key is the option name and the value is
             * another object which contains multiple information about the name, description value etc. of that
             * option. But to make a REST POST request to save the new option values, we need an object whose keys are
             * also the option names, but the values are just the new values. This is exactly what this computed
             * property provides.
             */
            optionValues: function () {
                let optionValues = {};
                for (let [optionName, optionData] of Object.entries(this.options)) {
                    optionValues[optionName] = optionData['value'];
                }
                return optionValues;
            }
        },
        /**
         * This method is executed as soon as the vue component was created. It is used to make request to the options
         * REST route to fetch the information about all available options. This is then saved to the this.options
         * object.
         *
         * @return void
         */
        created: function () {
            let self = this;
            this.api.getOptions()
            .then(function(options) {
                self.options = options;
            });
        }
    }
</script>

<style scoped>
    label {
        vertical-align: middle;
    }

    input {
        width: 100%;
    }
</style>