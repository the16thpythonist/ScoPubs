<template>
    <div class="multi-object-table-input">
        <div class="header row">
            <div v-for="(col, index) in columns" :key="index" class="col">
                {{ col["header"] }}
            </div>
        </div>
        <div class="object-entry row">
            <div v-for="([key, val], index) in Object.entries(value)">
                I am a row!
            </div>
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
                default:        "Multi Oject Table Input"
            }
        }
    }
</script>

<style scoped>

</style>