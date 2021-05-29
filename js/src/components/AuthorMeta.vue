<template>
    <div class="observed-author-meta">
        <ArrayTextInput
                    class="scopus-author-ids-input"
                    :value="[1, 2, 3]"
        />

        <MultiObjectTableInput
                    class="affiliation-input"
                    :value="dict"
                    :columns="columns"/>
    </div>
</template>

<script>
    // I have to realize the following inputs somehow:
    // - First name and last name: These I can do as simple text inputs
    // - Scopus author Ids: This is a list input, for which I can make a widget where you can easily add list elements
    //   and also remove them in some dynamic manner
    // - Affiliations: Now the affiliations are tricky. I am not even sure if the user should be able to edit these
    //   directly, since they are imported from scopus. But maybe just a widget which displays them?
    // - Affiliations whitelist/blacklist: Since only the blacklist is relevant I dont even need the fancy selection
    //   widget I did for the helmholtz plugin. I can literally just do a check box for the blacklisting.
    import ArrayTextInput from "./input/ArrayTextInput";
    import MultiObjectTableInput from "./input/MultiObjectTableInput";

    export default {
        name: "AuthorMeta",
        components: {
            ArrayTextInput,
            MultiObjectTableInput
        },
        data: function () {
            return {
                dict: {
                    10: {
                        "id": 10,
                        "name": "Hello",
                        "good": true
                    },
                    20: {
                        "id": 20,
                        "name": "World",
                        "good": false
                    }
                },
                columns: [
                    {
                        header: "ID",
                        get: function(object, key) { return object[key]["id"]; },
                        set: function(object, key, value) { object[key]["id"] = value; },
                        locked: true,
                        type: String
                    },
                    {
                        header: "Fancy Name",
                        get: function(object, key) { return object[key]["name"]; },
                        set: function(object, key, value) { object[key]["name"] = value; },
                        locked: false,
                        type: String
                    },
                    {
                        header: "Is it good?",
                        get: function(object, key) { return object[key]["good"]; },
                        set: function(object, key, value) { object[key]["good"] = value; },
                        locked: false,
                        type: Boolean
                    }
                ]
            }
        }
    }
</script>

<style scoped>

</style>