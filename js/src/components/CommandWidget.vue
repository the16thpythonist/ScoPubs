<template>
    <div class="command-widget">

        <div class="command-selection">
            <div>
                <em>Select</em> a command, <em>Enter parameters</em> and <em>execute</em> it in the background
                by pressing the button!
            </div>
            <select
                    v-model="selectedCommand">
                <option
                        v-for="(commandName, index) in Object.keys(commands)"
                        :selected="index === 0 ? 'selected' : 'hidden'"
                        :value="commandName">
                    {{ commandName }}
                </option>
            </select>
        </div>

        <div class="command-details">
            <div class="command-description" v-if="selectedCommand !== null">
                {{ commands[selectedCommand]['description'] }}
            </div>
            <div class="command-parameters" v-if="selectedCommand !== null">
                <div
                        class="command-parameter"
                        v-for="([parameterName, parameterData], index) in Object.entries(commands[selectedCommand]['parameters'])">
                    <div class="parameter-header">
                        <span class="parameter-name">{{ parameterData['name'] }}</span>
                        <span class="parameter-type">{{ parameterData['type'] }}</span>
                    </div>
                    <input type="text" v-model="parameters[parameterName]">
                </div>
            </div>
            <div class="command-placeholder" v-if="selectedCommand === null">
                Seems like you have not selected a command yet! Please use the selection widget above to select
                a command. The command details will appear in this box.
            </div>
        </div>

        <div class="command-execution">
            <button
                    class="execute button button-primary"
                    @click.prevent="onExecute">
                Execute Command!
            </button>
        </div>

        <div class="recent-commands">
            <div
                    class="recent-command"
                    v-for="logData in this.recentCommands">
                <strong>{{ logData['date'] }}</strong>: Command <em>"{{ logData['command_name'] }}"</em> has been
                started. <a :href="logData['edit_url']">View the log!</a>
            </div>
        </div>

    </div>
</template>

<script>
    /**
     * **THE BASIC IDEA**
     *
     * The command widget is used to interact with the command system. The command system is used to trigger long
     * running background processes on the server. These commands can take user parameters. This widget can be used
     * to select one of the available commands, enter custom parameters and then trigger the execution of the command.
     *
     * The command system is exposed via a REST interface, so this component will initially fetch the info about the
     * available commands from the REST interface of the server and then let the user choose one with a drop down
     * select element. Based on the chosen command, the details section of the widget will be dynamically created. The
     * detail section will display the description of the command and a series of input widgets, one for each parameter
     * expected by the command. Then there is a "execute" button. By pressing this button the selected command name and
     * the entered parameters are sent to the server as a REST POST request thereby triggering the actual execution
     * of the command.
     */
    import api from "../api.js";
    import Options from "./Options";

    export default {
        name: "CommandWidget",
        components: {Options},
        data: function() {
            return {
                api: new api.Api(),
                // "commands" will be an array, which contains objects for all the available commands. These objects
                // will describe their corresponding commands by containing fields about the name, description and a
                // specification of expected parameters.
                commands: {},
                // This array will contain a list of object, where each object provides the information about a
                // recently executed command.
                recentCommands: [],
                // This is bound to be the unique string identifier of the currently selected command of the user. It
                // will then be used to retrieve the information about the command from the "commands" object. This
                // information is then used to dynamically display the parameter inputs for example.
                selectedCommand: null,
                // Based on the selected Command, this object will contain a key value pair for each parameter which
                // that command expects. The values are mapped to the dynamically created input fields
                parameters: {}
            }
        },
        methods: {
            /**
             * The callback method for clicking the "execute" button. This will send a REST request to trigger the
             * execution of the currently selected result using all the current values of the parameter inputs as the
             * command parameters.
             *
             * @return void
             */
            onExecute: function() {
                this.api.executeCommand(this.selectedCommand, this.parameters);

                // After some time we request the recent commands again, so that the recent commands log updated with
                // the command which we have just executed. The user can then use the link of this entry to visit the
                // log file of the command which he just triggered.
                setTimeout(this.requestRecentCommands, 700);
            },
            /**
             * This function will send a request to the REST api to retrieve the list of recently executed commands and
             * then upon receiving the response, updated this.recentCommands accordingly.
             *
             * @return void
             */
            requestRecentCommands: function () {
                let self = this;
                this.api.getRecentCommands()
                .then(function(recentCommands) {
                    self.recentCommands = recentCommands;
                })
            },
            /**
             * This function will send a request to the REST api to retrieve the list of all available commands and
             * upon receiving the result sets it to this.commands.
             *
             * @return void
             */
            requestAvailableCommands: function () {
                let self = this;
                this.api.getAvailableCommands()
                .then(function(commands) {
                    self.commands = commands;
                })
            }
        },
        /**
         * This function gets called as soon as the component was created. We use this to make the initial queries to
         * to the REST Api to retrieve (a) the list of all available commands which we need for the selection widget
         * and (b) the information about the recently executed commands to display in the little log widget at the
         * bottom.
         */
        created: function() {
            this.requestAvailableCommands();
            this.requestRecentCommands();
        },
        watch: {
            selectedCommand: function(value) {
                this.parameters = {};
                for(let [parameterName, parameterData] of Object.entries(this.commands[value]['parameters'])) {
                    this.$set(this.parameters, parameterName, parameterData['default']);
                }
            }
        }
    }
</script>

<style lang="scss" scoped>

    /* Parameter definitions */

    $light_gray: #FAFAFA;
    $medium_gray: #A9A9A9;
    $dark_gray: #555555;

    /* Actual CSS */

    .command-widget {
        display: flex;
        flex-direction: column;
        font-size: 1.1em;
        align-items: stretch;
        justify-content: space-between;
    }

    .command-widget>div {
        margin-top: 10px;
    }

    .command-selection {
        display: flex;
        flex-direction: column;
    }

    .command-selection>select {
        margin-top: 10px;
    }

    .command-details, .recent-commands {
        background-color: $light_gray;
        padding: 5px;
        border-style: solid;
        border-color: $medium_gray;
        border-width: 1px;
    }

    .recent-commands {
        font-size: 0.9em;
        font-family: monospace;
    }

    .command-description {
        color: $dark_gray;
        margin-bottom: 5px;
    }

    .command-parameter {
        display: flex;
        flex-direction: column;
    }

    .parameter-header {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2px;
    }

    .parameter-type {
        font-size: 0.9em;
        font-family: monospace;
        color: $dark_gray;
    }

    .command-execution {
        display: flex;
        flex-direction: row;
        justify-content: center;
    }

    button.execute {
        font-size: 1.0em;
    }

</style>