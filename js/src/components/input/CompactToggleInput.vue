<template>
    <div class="compact-toggle-input">
        <span
                v-for="([key, active], index) in Object.entries(value)"
                :class="['toggle', String(active)]"
                :style="active ? trueStyle : falseStyle"
                @input="onInput"
                @click.prevent="onClick(key)">
            {{ key }}
        </span>
    </div>
</template>

<script>
    export default {
        name: "CompactToggleInput",
        props: {
            value: {
                type: Object,
                required: true
            },
            trueStyle : {
                type: Object,
                required: false,
                default: {}
            },
            falseStyle: {
                type: Object,
                required: false,
                default: true
            }
        },
        methods: {
            onClick: function(key) {
                this.value[key] = !this.value[key];
            },
            onInput: function() {
                this.$emit('input', this.value);
            }
        },
        data: function () {
            return {}
        }
    }
</script>

<style scoped>
    .compact-toggle-input {
        display: flex;
        flex-direction: row;
        border-style: solid;
        border-width: 1px;
    }

    .toggle {
        padding: 5px;
        padding-left: 10px;
        padding-right: 10px;
    }

    .toggle.true {
        background-color: green;
    }

    .toggle.false {
        background-color: red;
    }

    .toggle:hover {
        cursor: pointer;
        text-decoration: underline;
    }
</style>