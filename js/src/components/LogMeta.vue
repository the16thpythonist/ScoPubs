<template>
    <div class="log-meta">
        <div class="control-header">
            <CompactMultiToggleInput
                    v-model="enabledLevels"
                    :trueStyle="{backgroundColor: 'rgba(255, 255, 255, 0.2)'}"
                    :falseStyle="{backgroundColor: 'rgba(255, 255, 255, 0.0)'}"/>
            <div
                    class="indicator"
                    :class="String(log.running)">
            </div>
        </div>
        <div class="sep"></div>
        <div
                class="log-content">
            <span
                    v-for="(entry, index) in log.entries"
                    v-if="enabledLevels[entry['level']]"
                    :class="classesFromEntry(entry)">
                <span class="index">{{ String(index).padStart(3, '0') }}</span>
                <span class="log-message">{{ messageFromEntry(entry) }}</span>
            </span>
        </div>
    </div>
</template>

<script>
    import api from '../api.js';
    import CompactMultiToggleInput from "./input/CompactMultiToggleInput";

    export default {
        name: "LogMeta",
        components: {CompactMultiToggleInput},
        data: function() {
            return {
                api: new api.Api(),
                exists: false,
                log: {
                    'title':        '',
                    'running':      false,
                    'entries':      [],
                    'log_levels':   [],
                },
                enabledLevels: {
                }
            }
        },
        methods: {
            messageFromEntry(entry) {
                let level = entry['level'].toUpperCase().padEnd(7, ' ');
                return `${level} ${entry['time']}:: ${entry['message']}`;
            },
            classesFromEntry(entry) {
                console.log(entry);
                return [
                    'log-line',
                    entry['level']
                ]
            }
        },
        created: function() {
            let self = this;
            this.api.getLog(POST_ID)
            .then(function(log) {
                self.log = log;
                self.exists = true;

                for (let level of self.log['log_levels']) {
                    self.$set(self.enabledLevels, level, true);
                }
            })
            .catch( function(err) {
                self.exists = false;
            });
        }
    }
</script>

<style scoped>
    .log-meta {
        display: flex;
        flex-direction: column;
        color: white;
        background-color: #3A3E49;
        padding: 10px;
        border-style: solid;
        border-width: 1px;
        border-color: black;
    }

    .control-header {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }

    .indicator {
        content: " ";
        width: 20px;
        height: 20px;
        background-color: orangered;
        border-radius: 50%;
    }

    .indicator.true {
        background-color: lightgreen;
    }

    .log-content {
        font-family: "Bitstream Vera Sans Mono", monospace;
        display: flex;
        flex-direction: column;
        white-space: pre-wrap;
    }

    .log-line {
        margin-bottom: 3px;
    }

    .error {
        color: red;
    }

    .warning {
        color: yellow;
    }

    .debug {
        color: darkgray;
    }

    .index {
        color: gray;
        font-size: 0.9em;
        margin-right: 5px;
    }

    .sep {
        content: " ";
        height: 1px;
        background-color: white;
        margin-bottom: 10px;
        margin-top: 10px;
    }
</style>