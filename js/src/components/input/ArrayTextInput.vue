<template>
    <div class="array-text-input">
        <span>{{ title }}:</span>
        <div class="array-element" v-for="(value, index) in data" :key="index">
            <input
                    v-model="data[index]"
                    class="text-input"
                    type="text"
                    :ref="index"
                    :label="index"
                    :placeholder="placeholder"
                    @keypress.enter.prevent="()=>{}"
                    @keyup.enter.prevent="onEnter(index)"
                    @keyup.up="focusPreviousElement(index)"
                    @keyup.down="focusNextElement(index)"
                    @input="onInput">
            <button
                    type="button"
                    class="remove"
                    @click.prevent="onRemove(index)">
                -
            </button>
        </div>
        <button
                type="button"
                class="add"
                @click.prevent="onAdd()">
            +
        </button>
    </div>
</template>

<script>
    export default {
        name: "ArrayTextInput",
        props: {
            // This is the central data structure for the widget. This array will be the subject of the input field.
            // its elements will be displayed as text inputs which can be edited.
            value: {
                type:       Array,
                required:   true
            },
            // This should be a string description which briefly states what the content of the array is. It will be
            // displayed above the actual array elements input listing.
            title: {
                type:       String,
                required:   false,
                default:    "Array Text Input"
            },
            // The text input placeholder string description which should be displayed when a text input field is
            // empty.
            placeholder: {
                type:       String,
                required:   false,
                default:    "enter data"
            },
            // This is the default value which is supposed to be used as the value of any element which is added
            // to the array. The reason why this is actually a callback and not just a simple value is two-fold:
            // (1) In this way it can be any custom type. (2) More flexibility: By having it be a callback you could
            // realize random default values for example.
            default: {
                type:       Function,
                required:   false,
                default:    function () {
                    return "";
                }
            }
        },
        data: function () {
            return {
                data:       this.value,
                length:      this.value.length
            }
        },
        methods: {
            /**
             * Gets called whenever one of the input event is emitted on any of the text inputs for any of the elements
             * of the array. This method will in turn also emit the "input" event using the "data" attribute as the
             * parameter to pass along. This is necessary so that higher level components which use this component can
             * use the v-model directive.
             */
            onInput: function () {
                this.$emit('input', this.data)
            },
            /**
             * The callback method for the "remove" button associated with each input element. Pressing this button
             * should remove the corresponding array element from the array. Emits an input event.
             *
             * @param index The integer index of the array element to delete
             */
            onRemove: function (index) {
                // We use this special method which is offered by Vue components to delete the element here because
                // this method guarantees that the observers on the element are processed correctly, which would not be
                // the case if we blatently deleted it ourselves.
                this.$delete(this.data, index);
                this.length -= 1;
                // Deleting an element represents a change of the array, thus we trigger input event here to inform all
                // listeners of this state change.
                this.onInput();
            },
            /**
             * Callback for the "add" button. This button should add a new input field to the display so that the user
             * is able to add a new value to the array. Emits the input event.
             */
            onAdd: function() {
                // "default" is a callback which returns the default value for any new element of the array.
                let value = this.default();
                this.$set(this.data, this.length, value);
                this.length += 1;
                this.onInput();
            },
            /**
             * The callback method for pressing the "enter" key when inside the current input field given by index. If
             * the element is the last element, creates a new element and focuses it. If the element is not the last,
             * simply switches the focus to the next element.
             *
             * @param index
             */
            onEnter: function(index) {
                // If the element is the last element in the list we want enter to automatically create a new entry
                // to create an editing experience which is as smooth as possible for the user
                if (index === this.length - 1) {
                    this.onAdd();
                }

                // Enter should exit the current element and jump to the next one
                this.focusNextElement(index);
            },
            /**
             * Relative to the element with the given index, sets the focus to the next element if possible.
             *
             * @param index
             */
            focusNextElement: function(index) {
                if (index !== this.length - 1) {
                    this.$nextTick(function() {
                        // If you review the template section: Every input field has its "ref" value dynamically set
                        // to be its index in the for loop. This code thus selects the next one relative to the given
                        // element and focuses that element.
                        this.focusIndex(index + 1);
                    });
                }
            },
            /**
             * Relative to the element with the given index, sets the focus to the previous element if possible.
             *
             * @param index
             */
            focusPreviousElement: function(index) {
                if (index !== 0) {
                    this.$nextTick(function() {
                        this.focusIndex(index - 1);
                    });
                }
            },
            /**
             * Sets the current focus to the input element with the given index.
             *
             * @param index
             */
            focusIndex: function(index) {
                let element = this.$refs[`${index}`][0];
                let elementValue = element.value;
                element.focus();
                element.value = elementValue;
            }
        },
        watch: {
            value: function(newValue) {
                this.data = newValue;
                this.length = newValue.length;
            }
        }
    }
</script>

<style scoped>

    .array-element {
        display: flex;
        flex-direction: row;
        margin-bottom: 5px;
    }

    input {
        flex: 2;
        margin-right: 10px;
    }

    button {
        border-style: solid;
        border-width: 1px;
        width: 30px;
        height: 30px;
        background-color: white;
        color: white;
        font-weight: bold;
        font-size: 1.2em;
        cursor: pointer;
    }

    button:hover {
        background-color: #f5f5f5;
        opacity: 80%;
    }

    button.add {
        color: #46B450;
        border-color: #46B450;
    }

    button.remove {
        color: #dc3232;
        border-color: #dc3232;
    }

</style>