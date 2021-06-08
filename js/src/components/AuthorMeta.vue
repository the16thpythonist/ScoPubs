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
            <input type="text" placeholder="Max" v-model="author['first_name']">
        </label>

        <label>
            <span>Last Name:</span>
            <input type="text" placeholder="Mustermann" v-model="author['last_name']">
        </label>

        <h2>Scopus Author Information</h2>

        <p>
            Use the following input widget to supply a list of scopus author ID's which identify the author within
            the scopus database.
        </p>

        <ArrayTextInput
                    id="scopus-author-ids-input"
                    title="Scopus Author IDs"
                    v-model="author['scopus_author_ids']"/>

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
                    v-model="author['affiliations']"
                    :columns="columns"/>

        <button
                    class="button button-primary button-large"
                    @click.prevent="onSave()">
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
            let self = this;

            return {
                // This attribute will be used to store the information about wheter the widget is displayed for a
                // post which already exists or for creating a new post. This information will determine what action
                // to take when the publish button is pressed -> posting a completely new author object or simply
                // modifying an existing one?
                exists: false,
                // This author object will be the central data structure of this component: All input widgets will
                // modify it's properties. The actual values of these properties will be retrieved via a REST GET
                // request from the server, but initializing all the values here as empty is actually important!
                // If this was not the case the program would crash in the initial moment before the actual data was
                // retrieved from the server.
                author: {
                    'first_name':               '',
                    'last_name':                '',
                    'scopus_author_ids':        [],
                    'affiliations':             {},
                    'affiliation_blacklist':    []
                },
                // This is the wrapper object for interacting with the wordpress REST backend of our custom post types
                api: new api.Api(),
                // This array is the secondary data structure required by MultiObjectTableInput component. This
                // component is v-model bound to author['affiliations'] to modify the object. This input
                // component constructs a table of multiple inputs to essentially modify an object whose properties
                // are again objects or in python terms a "Dict[Any, dict]" structure. It needs the additional
                // information about which columns to display -> which properties of the nested dict to modify. This
                // is exactly what this array provides. Each element of this array is an object which defines how one
                // of the tables columns works (-> which attribute it modifies)
                columns: [
                    {
                        header: "Affiliation ID",
                        get: function(getter, object, key) { return getter(object[key], ["id"]); },
                        set: function(setter, object, key, value) { setter(object[key], "id", value); },
                        locked: true,
                        key: true,
                        type: String
                    },
                    {
                        header: "Name of Institution",
                        get: function(getter, object, key) { return getter(object[key], ["name"]); },
                        set: function(setter, object, key, value) { setter(object[key], "name", value); },
                        locked: false,
                        type: String
                    },
                    {
                        header: "City",
                        get: function(getter, object, key) { return getter(object[key], ["city"]); },
                        set: function(setter, object, key, value) { setter(object[key], "city", value); },
                        locked: false,
                        type: String
                    },
                    {
                        header: "Blacklist?",
                        get: function(getter, object, key) { return getter(object[key], ["blacklist"]); },
                        set: function(setter, object, key, value) {
                            // This is actually a great example why the "MultiObjectTableInput" is so powerful!
                            // I have to admit, that the way you have to define the columns is a lot. Especially the
                            // getter and setter. For most use cases this will be the same boilerplate code, but with
                            // this one it is actually super useful. So this set function is called every time the
                            // blacklist value is modified -> We can use this information to update our
                            // "affiliation_blacklist" property of the author object in real time!
                            setter(object[key], "blacklist", value);

                            let index = self.author['affiliation_blacklist'].indexOf(key);
                            // This is the case for if the element is being blacklisted but not already contained in
                            // the actual blacklist. In this case we need to add it
                            if (value === true && index === -1) {
                                self.author['affiliation_blacklist'].push(key);
                            }
                            // The second important case is if the element is being removed from the blacklist but is
                            // part of the actual list. Then we need to remove it from the list
                            if (value === false && index > -1) {
                                self.author['affiliation_blacklist'].splice(index, 1);
                            }
                        },
                        locked: false,
                        type: Boolean
                    }
                ],
            }
        },
        methods: {
            /**
             * The callback method for pressing the save button at the end of the meta box. Actually this method also
             * acts as the callback for the general "Publish" button of the wordpress edit page.
             *
             * Based on the state of the "exists" flag (whether the current page is to create a new post or modify an
             * existing post) this method will use the current state of the "author" object to either modify the post
             * or create a new post.
             */
            onSave: function() {
                if (this.exists) {
                    this.api.updateObservedAuthor(POST_ID, this.author);
                    setTimeout(function() {
                       window.location.reload();
                    }, 500);
                } else {
                    this.api.postObservedAuthor(this.author);
                    setTimeout(function() {
                        window.location.replace(WP['admin_url'] + `post.php?post=${POST_ID + 1}&action=edit`);
                    }, 500);
                }
            }
        },
        /**
         * This function is called as soon as the component is created.
         *
         * It's main purpose is to fetch the actual data for the author of the current post from the REST API of the
         * server and update the internal "author" object. It also redefines the callback for the wordpress edit page
         * "publish" button to be the local function "onSave".
         */
        created: function () {
            // First of all we need to fetch the meta information about the author from the REST api
            let self = this;
            this.api.getObservedAuthor(POST_ID).then(function (author) {
                self.author = author;
                self.exists = true;
            }).catch(function (error) {
                self.exists = false;
            });

            // Now my idea is to attach an additional step to the general "publish" process here.
            let publishButton = document.getElementById('publish')
            let flag = false;
            publishButton.onclick = function() {
                if (!flag) {
                    self.onSave();
                    //flag = true;
                    //publishButton.click();
                    return false;
                }

            }
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