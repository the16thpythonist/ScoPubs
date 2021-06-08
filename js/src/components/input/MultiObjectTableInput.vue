<template>
    <div class="multi-object-table-input">

        <!--
        We'll only show the title if it is not an empty string. This provides the option of removing the
        the title completely if it is not required.
        -->
        <span v-if="title !== ''">{{ title }}:</span>


        <div class="header row">
            <div v-for="(col, index) in columns" :key="index" class="col">
                {{ col["header"] }}
            </div>
            <span style="width: 30px;"></span>
        </div>

        <!--
        This section is essentially two nested loops which construct the table itself. The first loop goes through all
        the actual entries of the object as each entry in the object is supposed to be represented by one row. The
        second loop goes through all the defined columns for each entry of the main data object and then uses the
        the "get" function provided with each column definition to extract the actual value for that entry and that
        column. This is then used as the basis for an appropriate input widget (defined by the data type specified for
        that row.
        -->
        <div
                v-for="([key, val], index) in Object.entries(data)"
                class="object-entry row"
                v-if="Object.keys(data).length !== 0">
            <div v-for="(col, index) in columns" class="col">
                <input
                        v-if="col['type'] === String"
                        type="text"
                        :disabled="col['locked']"
                        :value="col['get']((o, k) => o[k], data, key)"
                        @input="onTextInput($event, data, key, col)">
                <!--
                For a boolean type value the most appropriate input method is a checkbox. A checkbox has to be treated
                differently though. For one thing: It's boolean state value is saved as the "checked" property and not
                as the "value". This also implies that we'll need a different input callback which extracts this
                checked property from the event.
                -->
                <input
                        v-if="col['type'] === Boolean"
                        type="checkbox"
                        :disabled="col['locked']"
                        :checked="!!col['get']((o, k) => o[k], data, key)"
                        @input="onCheckboxInput($event, data, key, col)">
            </div>

            <button
                        class="col remove"
                        @click.prevent="onRemove(key)">
                -
            </button>
        </div>
        <!--
        Checking for the length of an objects keys is essentially checking if the object is empty or not. If the object
        Is in fact empty, we display the "empty" message as the single row, which should contain a message to the user
        of what it means if the object is empty.
        -->
        <div
                class="empty row"
                v-if="Object.keys(data).length === 0">
            {{ empty }}
        </div>

        <div class="add row">
            <div v-for="(col, index) in columns" class="col">
                <input
                        v-if="col['type'] === String"
                        type="text"
                        :value="col['get']((o, k) => o[k], temp, 0)"
                        @input="onTextInput($event, temp, 0, col)">
                <!--
                For a boolean type value the most appropriate input method is a checkbox. A checkbox has to be treated
                differently though. For one thing: It's boolean state value is saved as the "checked" property and not
                as the "value". This also implies that we'll need a different input callback which extracts this
                checked property from the event.
                -->
                <input
                        v-if="col['type'] === Boolean"
                        type="checkbox"
                        :checked="!!col['get']((o, k) => o[k], temp, 0)"
                        @input="onCheckboxInput($event, temp, 0, col)">
            </div>
            <button
                    class="col add"
                    @click.prevent="onAdd()">
                +
            </button>
        </div>

    </div>
</template>

<script>
    export default {
        name: "MultiObjectTableInput",
        props: {
            value: {
                type:           Object,
                required:       true
            },
            columns: {
                type:           Array,
                required:       true
            },
            title: {
                type:           String,
                required:       false,
                default:        "Multi Object Table Input"
            },
            empty: {
                type:           String,
                required:       false,
                default:        "Seems like the object in question is an empty object..."
            },
            add: {
                type:           Function,
                required:       false,
                default:        function(setter, object, key) {
                    setter(object, key, {});
                }
            }
        },
        data: function (){
            // Here we should check if the passed "columns" data structure is correct.

            return {
                data: this.value,
                temp: {0: {}}
            }
        },
        methods: {
            onTextInput: function(event, object, key, column) {
                let value = event.target.value;
                column['set'](this.$set, object, key, value);
            },
            onCheckboxInput: function(event, object, key, column) {
                let value = event.target.checked;
                column['set'](this.$set, object, key, value);
            },
            onRemove: function(key) {
                this.$delete(this.data, key);
            },
            onAdd: function() {
                // 1. Finding out the key
                // To add a new key value pair to the main data object we first need to figure out which key to use.
                // One of the column definitions of the "columns" array should contain the entry "key=true". This
                // will tell us to use the value of that input field of the temporary row as the key.
                let key = '';
                for (let [_, column] of Object.entries(this.columns)) {
                    if (column.hasOwnProperty('key')) {
                        if (column['key'] === true) {
                            // Here we are getting the actual value from the temporary row for this column which is
                            // marked as containing the key. We save the key for upcoming operations.
                            key = column['get']((o, k) => o[k], this.temp, 0);
                            break;
                        }
                    }
                }

                // 2. Setting the new entry up in main data object
                // the "add" function is a property of this component, which ideally gets passed in as a custom argument
                // from the parent component. It essentially defines how a new entry of the main data object needs to
                // be properly initialized (There may be other hidden or derived fields aside from those manipulated by
                // this components input fields).
                // Essentially after this function is done, we can assume, that we have a new empty object entry in the
                // main data object which has the key we have previously found out
                this.add(this.$set, this.data, key);

                // 3. Transferring the actual values from the temp
                // At this point, the user has already entered the values for this new row and these are currently still
                // stored in the temp row object. This loop essentially goes through all value of this temp folder and
                // "copying" them to the main data object entry.
                for (let [_, column] of Object.entries(this.columns)) {
                    let tempValue = column['get']((o, k) => o[k], this.temp, 0);
                    column['set'](this.$set, this.data, key, tempValue);
                }

                // Then at last of course we have to reset the temp row so that it is ready for adding a new row.
                this.$set(this.temp, 0, {});
            }
        },
        watch: {
            value: function(newVal, oldVal) {
                this.data = newVal;
            }
        }
    }
</script>

<style scoped>

    div.multi-object-table-input {
        display: flex;
        flex-direction: column;
    }

    div.row {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        padding-bottom: 5px;
        padding-top: 5px;
        /* A border helps to differentiate the individual rows */
        border-style: solid;
        border-width: 0;
        border-bottom-width: 1px;
        border-bottom-color: #c3c3c3;
    }

    div.row.header {
        background-color: #d7d7d7;
        font-size: 1.1em;
    }

    div.row.empty {
        font-size: 1.1em;
        padding-left: 15px;
        padding-top: 5px;
        padding-bottom: 5px;
        color: #82878c;
    }

    div.col {
        flex: 1;
        margin-left: 10px;
        align-self: center;
    }

    div.col>input[type=text] {
        width: 80%;
    }

    button.col {
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

    button.col:hover {
        background-color: #f5f5f5;
        opacity: 80%;
    }

    button.col.add {
        color: #46B450;
        border-color: #46B450;
    }

    button.col.remove {
        color: #dc3232;
        border-color: #dc3232;
    }

</style>