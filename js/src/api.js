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
    this.publicationEndpoint = "wp/v2/" + WP["publication_post_type"] + "/"

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
        // We only need to fix one thing: Curiously, the "affiliations" field is defined as an object type value
        // and it works fine except for when there are no entries: Instead of giving an empty object it gives and empty
        // array?
        if (typeof(data['affiliations'] === 'array') && data['affiliations'].length === 0) {
            data['affiliations'] = {};
        }

        return data;
    })
}

Api.prototype.updateObservedAuthor = function(postID, author) {
    return this.post(this.authorEndpoint + postID, {
        'id':                           postID,
        'type':                         WP['author_post_type'],
        'title':                        `${author["last_name"]}, ${author["first_name"]}`,
        'meta': {
            'first_name':               author['first_name'],
            'last_name':                author['last_name'],
            'scopus_author_ids':        author['scopus_author_ids'],
            'affiliations':             author['affiliations'],
            'affiliations_blacklist':   author['affiliation_blacklist']
        }
    })
}

Api.prototype.postObservedAuthor = function(author) {
    return this.post(this.authorEndpoint, {
        'type':                         WP['author_post_type'],
        'status':                       'publish',
        'title':                        `${author["last_name"]}, ${author["first_name"]}`,
        'meta': {
            'first_name':               author['first_name'],
            'last_name':                author['last_name'],
            'scopus_author_ids':        author['scopus_author_ids'],
            'affiliations':             author['affiliations'],
            'affiliations_blacklist':   author['affiliation_blacklist']
        }
    })
}

Api.prototype.getPublication = function(postID) {
    return this.get(this.publicationEndpoint + postID).then(function (data) {
        return data;
    })
}

Api.prototype.postPublication = function(publication) {
    return this.post(this.publicationEndpoint, {
        'type':                     WP['publication_post_type'],
        'status':                   'publish',
        'title':                    publication['title'],
        'content':                  publication['abstract'],
        'meta': {
            'scopus_id':            publication['scopus_id'],
            'kitopen_id':           publication['kitopen_id'],
            'doi':                  publication['doi'],
            'eid':                  publication['eid'],
            'issn':                 publication['issn'],
            'journal':              publication['journal'],
            'volume':               publication['volume'],
            'author_count':         publication['author_count'],
            'authors':              publication['authors']
        }
    })
}

Api.prototype.updatePublication = function(postID, publication) {
    let data = {
        'id': postID,
        'type': WP['publication_post_type'],
        'status': 'publish',
        'title': publication['title'],
        'content': publication['abstract'],
        'meta': {
            'scopus_id': publication['scopus_id'],
            'kitopen_id': publication['kitopen_id'],
            'doi': publication['doi'],
            'eid': publication['eid'],
            'issn': publication['issn'],
            'journal': publication['journal'],
            'volume': publication['volume'],
            'author_count': publication['author_count'],
            'authors': publication['authors']
        }
    };
    console.log(data);
    return this.post(this.publicationEndpoint + postID, data
    ).catch(function (error) {
        console.log(error);
        return error;
    });
}


export default {
    Api: Api
}