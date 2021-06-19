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
                    class="execute"
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
    import api from "../api.js";
    import Options from "./Options";

    export default {
        name: "CommandWidget",
        components: {Options},
        data: function() {
            return {
                api: new api.Api(),
                commands: [],
                recentCommands: [],
                selectedCommand: null,
                parameters: {}
            }
        },
        methods: {
            onExecute: function() {
                this.api.executeCommand(this.selectedCommand, this.parameters);
            }
        },
        created: function() {
            let self = this;

            this.api.getAvailableCommands()
            .then(function(commands) {
                self.commands = commands;
                console.log(commands);
            })

            this.api.getRecentCommands()
            .then(function(recentCommands) {
                self.recentCommands = recentCommands;
                console.log(recentCommands);
            })
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

</style>