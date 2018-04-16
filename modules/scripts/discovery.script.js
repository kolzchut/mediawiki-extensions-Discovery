(function(){

    var MAX_CHARS = 85;

    var Discovery = {
        buildDOM: function(data){
            var finalDOM = $('<div></div>');

            if(!data) return;

            $.each(data.seeAlso, function(i, e){
                var item = this.buildDiscoveryItem(e);
                finalDOM.append(item);
            }.bind(this));

            $.each(data.ads, function(i, e){
                if(i == 0) {
                    finalDOM.prepend(this.buildDiscoveryItem(e));
                } else {
                    finalDOM.append(this.buildDiscoveryItem(e));
                }
            }.bind(this));

            return finalDOM;
        },
        buildDiscoveryItem: function(item){
            var currentItem = $(this.template);

            if(item.indicators) {
                var itemKeys = Object.keys(item.indicators);
                $.each(itemKeys, function(i, e){
                    if(item.indicators[e] === 1) {
                        currentItem.find('.discovery-tags').append('<span class="discovery-tag discovery-tag-' + e + '"></span>');
                    }
                });
            }

            currentItem.find('.discovery-link').attr('href', item.url);
            currentItem.find('.discovery-text').text(item.content.length > MAX_CHARS ? item.content.substring(0, MAX_CHARS) + '...' : item.content);

            return currentItem;
        },
        template: '<div class="discovery-item"><a class="discovery-link"><div class="discovery-tags"></div><div class="discovery-text"></div></a></div>',
    };


    $(document).ready(function () {

        $.ajax({
            method: 'GET',
            data: {
                action: 'discovery',
                title: mw.config.get('wgTitle'),
                format: 'json'
            },
            url: mw.config.get('wgServer') + mw.config.get('wgScriptPath') + '/api.php'
        })
        .then(function (response) {
            var discoveryDOM = Discovery.buildDOM(response.discovery);

            $('.discovery').append(discoveryDOM);
        })

    });

})();