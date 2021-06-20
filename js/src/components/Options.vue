<template>
    <div class="options">
        Options!!
    </div>
</template>

<script>
    import api from '../api.js';

    export default {
        name: "Options",
        data: function() {
            return {
                api: new api.Api(),
                options: {}
            }
        },
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

</style>