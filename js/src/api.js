/* eslint-disable */
import axios from 'axios';


// API CLASS
// ====================================================================================================================
// Overview of using axios for making API requests:
// https://github.com/axios/axios
// Authentication considerations:
// https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
// Reference for working with posts and the rest api:
// https://developer.wordpress.org/rest-api/reference/posts/#update-a-post

function Api() {
    this.url = WP["rest_url"];
    this.authorEndpoint = "wp/v2/" + WP["author_post_type"] + "/"

    this.nonce = WP["nonce"];
}

Api.prototype.makeURL = function(endpoint) {
    if (endpoint[0] === "/") {
        // https://stackoverflow.com/questions/4564414/delete-first-character-of-a-string-in-javascript
        return this.url + endpoint.substring(1);
    } else {
        return this.url + endpoint;
    }
}

Api.prototype.get = function(endpoint) {
    let url = this.makeURL(endpoint);
    return axios.get(url).then(function (response) {
        return response.data;
    })
}

Api.prototype.post = function(endpoint, data) {
    return axios({
        method: "POST",
        headers: {"X-WP-Nonce": this.nonce},
        url: this.makeURL(endpoint),
        data: data
    })
}

Api.prototype.getObservedAuthor = function(postID) {
    return this.get(this.authorEndpoint + postID).then(function (data) {
        console.log(data);
        return data;
    })
}

Api.prototype.updateObservedAuthor = function(postID, author) {
    return this.post(this.authorEndpoint + postID, {
        "id":                           postID,
        "title":                        "Teufel, Jonas",
        "meta": {
            "first_name":               "Jonas",
            "last_name":                "Teufel",
            "scopus_author_ids":        [1, 2],
            "affiliations":             {"hello": "world"},
            "affiliations_blacklist":   [1]
        }
    })
}


export default {
    Api: Api
}