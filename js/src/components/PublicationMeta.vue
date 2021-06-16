<template>
    <div class="publication-meta">
        <h2>Scopus Data</h2>

        <label>
            <span>Scopus ID:</span>
            <input type="text" v-model="publication['scopus_id']">
        </label>

        <label>
            <span>DOI:</span>
            <input type="text" v-model="publication['doi']">
        </label>

        <label>
            <span>EID:</span>
            <input type="text" v-model="publication['eid']">
        </label>

        <label>
            <span>ISSN:</span>
            <input type="text" v-model="publication['issn']">
        </label>

        <label>
            <span>Journal:</span>
            <input type="text" v-model="publication['journal']">
        </label>

        <h2>KITOpen Data</h2>

        <label>
            <span>KITOpenID</span>
            <input type="text" v-model="publication['kitopen_id']">
        </label>

        <h2>Author Information</h2>

        <label>
            <span>Author Count:</span>
            <input type="number" v-model="publication['author_count']">
        </label>

        <MultiObjectTableInput
                    id="authors-input"
                    title="Authors"
                    v-model="publication['authors_object']"
                    :columns="columns"/>

        <button
                class="button button-primary button-large"
                @click.prevent="onSave()">
            Save Changes
        </button>
    </div>
</template>

<script>
    import MultiObjectTableInput from "./input/MultiObjectTableInput";
    import api from "../api.js";

    export default {
        name: "PublicationMeta",
        components: {MultiObjectTableInput},
        data: function() {
            return {
                exists: false,
                publication: {
                    'title':            '',
                    'abstract':         '',
                    'scopus_id':        '',
                    'kitopen_id':       '',
                    'doi':              '',
                    'eid':              '',
                    'issn':             '',
                    'journal':          '',
                    'volume':           '',
                    'author_count':     0,
                    'authors':          [],
                    'authors_object':   {}
                },
                api: new api.Api(),
                columns: [
                    {
                        header: 'Index',
                        get: function(getter, object, key) { return getter(object[key], ["index"]); },
                        set: function(setter, object, key, value) { setter(object[key], "index", value); },
                        locked: true,
                        key: true,
                        type: String
                    },
                    {
                        header: 'Full Name',
                        get: function(getter, object, key) { return getter(object[key], ["full_name"]); },
                        set: function(setter, object, key, value) { setter(object[key], "full_name", value); },
                        locked: false,
                        type: String
                    },
                    {
                        header: 'Affiliation ID',
                        get: function(getter, object, key) { return getter(object[key], ["affiliation_id"]); },
                        set: function(setter, object, key, value) { setter(object[key], "affiliation_id", value); },
                        locked: false,
                        type: String
                    }
                ],
            }
        },
        methods: {
            onSave() {
                let titleInput = document.getElementById('title');
                this.publication['title'] = titleInput.value;

                let contentInput = document.getElementById('content')
                this.publication['abstract'] = contentInput.value;

                this.publication['authors'] = Object.values(this.publication['authors_object']);

                if (this.exists) {
                    this.api.updatePublication(POST_ID, this.publication);
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    this.api.postPublication(this.publication);
                    setTimeout(function() {
                        window.location.replace(WP['admin_url'] + `post.php?post=${POST_ID + 1}&action=edit`);
                    }, 500);
                }
            }
        },
        created: function () {
            let self = this;
            this.api.getPublication(POST_ID)
            .then(function (publication) {
                self.exists = true;
                self.publication = publication;
                self.publication['authors_object'] = {};
                let index = 0;
                for (let author of self.publication['authors']) {
                    author['index'] = index;
                    self.publication['authors_object'][index] = author;
                    index++;
                }
                console.log(self.publication)
            })
            .catch(function (error) {
                self.exists = false;
            })

            // Now my idea is to attach an additional step to the general "publish" process here.
            let publishButton = document.getElementById('publish')
            let flag = false;
            publishButton.onclick = function() {
                if (!flag) {
                    self.onSave();
                    return false;
                }
            }
        }
    }
</script>

<style scoped>
    .publication-meta {
        display: flex;
        flex-direction: column;
    }

    .publication-meta>h2 {
        padding: 5px 5px 5px 0 !important;
        margin: 20px 0 20px 0 !important;
        border-bottom: 1px solid #b4b9be;
    }

    label {
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
    }

    label>input {
        width: 50%;
    }
</style>