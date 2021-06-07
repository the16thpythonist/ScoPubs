<template>
    <div class="observed-author-meta">

        <h2>Personal Information</h2>

        <p>
            Please enter the first and last name of the author below. It is important that the name is spelled
            correctly! The full name of the author is whats used to identify the author and retrieve publications from
            the KITOpen database.<br>
            Upon saving this author post, it's title will also be automatically be set to the supplied name.
        </p>

        <label>
            <span>First Name:</span>
            <input type="text" placeholder="Max">
        </label>

        <label>
            <span>Last Name:</span>
            <input type="text" placeholder="Mustermann">
        </label>

        <h2>Scopus Author Information</h2>

        <p>
            Use the following input widget to supply a list of scopus author ID's which identify the author within
            the scopus database.
        </p>

        <ArrayTextInput
                    id="scopus-author-ids-input"
                    title="Scopus Author IDs"
                    :value="[1, 2, 3]"/>

        <p>
            The following widget will display the information about the authors affiliations. When adding a new author
            it will be empty, the affiliations have to be requested from the scopus database first and are based on the
            supplied author ID's, so make sure they are correct. To update the affiliations of an author run the
            according server command.<br>
            The last column can be used to put a certain
            affiliation on the blacklist. Any publication of the author while being affiliated with this entry will not
            be imported.
        </p>

        <MultiObjectTableInput
                    id="affiliation-input"
                    title="Author Affiliations"
                    :value="dict"
                    :columns="columns"/>

        <button
                    class="button button-primary button-large">
            Save Changes
        </button>
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
    import api from "../api.js";

    export default {
        name: "AuthorMeta",
        components: {
            ArrayTextInput,
            MultiObjectTableInput
        },
        data: function () {
            let a = new api.Api();
            console.log(a.makeURL("posts"));
            console.log(a.makeURL("/posts"))
            a.getObservedAuthor(POST_ID);
            a.updateObservedAuthor(POST_ID, {});

            return {
                api: new api.Api(),
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
                        get: function(getter, object, key) { return getter(object[key], ["id"]); },
                        set: function(setter, object, key, value) { setter(object[key], "id", value); },
                        locked: true,
                        key: true,
                        type: String
                    },
                    {
                        header: "Fancy Name",
                        get: function(getter, object, key) { return getter(object[key], ["name"]); },
                        set: function(setter, object, key, value) { setter(object[key], "name", value); },
                        locked: false,
                        type: String
                    },
                    {
                        header: "Is it good?",
                        get: function(getter, object, key) { return getter(object[key], ["good"]); },
                        set: function(setter, object, key, value) { setter(object[key], "good", value); },
                        locked: false,
                        type: Boolean
                    }
                ],
            }
        },
        methods: {
            onSave: function() {
                console.log("saving");
            }
        },
        created: function () {
            console.log("author meta created!");
            // Now my idea is to attach an additional step to the general "publish" process here.
        }
    }
</script>

<style scoped>
    .observed-author-meta {
        display: flex;
        flex-direction: column;
    }

    .observed-author-meta>h2 {
        padding: 5px 5px 5px 0 !important;
        margin: 20px 0 20px 0 !important;
        border-bottom: 1px solid #b4b9be;
    }

    label {
        margin-bottom: 5px;
    }

    label>input {
        margin-left: 10px;
        width: 50%;
    }

    #scopus-author-ids-input {
        margin-top: 20px;
        margin-bottom: 20px;
    }

    #affiliation-input {
        margin-top: 20px;
        margin-bottom: 20px;
    }
</style>