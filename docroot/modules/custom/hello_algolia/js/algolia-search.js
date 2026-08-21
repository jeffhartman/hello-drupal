(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.helloAlgolia = {
    attach(context) {
      once('hello-algolia', '.algolia-search', context).forEach(
        (searchElement) => {
          const applicationId =
            searchElement.dataset.algoliaApplicationId;
          const searchApiKey =
            searchElement.dataset.algoliaSearchApiKey;
          const indexName =
            searchElement.dataset.algoliaIndexName;

          if (!applicationId || !searchApiKey || !indexName) {
            console.error(
              'Algolia search could not start because its configuration is incomplete.',
            );
            return;
          }

          if (
            typeof algoliasearch === 'undefined' ||
            typeof instantsearch === 'undefined'
          ) {
            console.error('The Algolia JavaScript libraries did not load.');
            return;
          }

          const searchClient = algoliasearch(
            applicationId,
            searchApiKey,
          );

          const search = instantsearch({
            indexName,
            searchClient,
            future: {
              preserveSharedStateOnUnmount: true,
            },
          });

          search.addWidgets([
            instantsearch.widgets.searchBox({
              container: '#algolia-searchbox',
              placeholder: 'Search content',
              showReset: true,
              showSubmit: true,
              showLoadingIndicator: true,
            }),

            instantsearch.widgets.stats({
              container: '#algolia-stats',
            }),

            instantsearch.widgets.clearRefinements({
              container: '#algolia-clear-refinements',
              templates: {
                resetLabel: 'Clear filters',
              },
            }),

            instantsearch.widgets.refinementList({
              container: '#algolia-operator-name',
              attribute: 'operator_name',
              limit: 15,
              showMore: true,
              showMoreLimit: 20,
              sortBy: ['name:asc', 'count:desc'],
              templates: {
                showMoreText({ isShowingMore }, { html }) {
                  return html`${isShowingMore ? 'Show less' : 'Show more'}`;
                },
              },
            }),

            instantsearch.widgets.sortBy({
              container: '#algolia-sort',
              items: [
                {
                  label: 'Relevance',
                  value: 'ships',
                },
                {
                  label: 'Ship Name: A–Z',
                  value: 'ships_title_asc',
                },
                {
                  label: 'Ship Name: Z–A',
                  value: 'ships_title_desc',
                },
                {
                  label: 'Operator: A–Z',
                  value: 'ships_operator_name_asc',
                },
                {
                  label: 'Operator: Z–A',
                  value: 'ships_operator_name_desc',
                },
              ],
            }),

            instantsearch.widgets.hits({
              container: '#algolia-hits',
              templates: {
                empty(results, { html }) {
                  return html`
                    <div class="algolia-search__empty">
                      No results were found for
                      <strong>${results.query}</strong>.
                    </div>
                  `;
                },

                item(hit, { html, components }) {
                  return html`
                    <article class="algolia-hit">
                      <h2 class="algolia-hit__title">
                        <a href="${hit.url || '#'}">
                          ${components.Highlight({
                    hit,
                    attribute: 'title',
                  })}
                        </a>
                      </h2>

                      ${
                    hit.summary
                      ? html`
                              <div class="algolia-hit__summary">
                                ${components.Snippet({
                        hit,
                        attribute: 'summary',
                      })}
                              </div>
                            `
                      : ''
                  }

                      ${
                    hit.field_imo_number
                      ? html`
                              <div class="algolia-hit__imo_number">
                                ${hit.field_imo_number}
                              </div>
                            `
                      : ''
                  }
                      ${
                    hit.operator_name
                      ? html`
                          <div class="algolia-hit__operator_name">
                            ${hit.operator_name}
                          </div>
                        `
                      : ''
                  }
                    </article>
                  `;
                },
              },
            }),

            instantsearch.widgets.pagination({
              container: '#algolia-pagination',
              padding: 2,
              showFirst: false,
              showLast: false,
              scrollTo: '#algolia-searchbox',
            }),
          ]);

          search.start();
        },
      );
    },
  };
})(Drupal, once);
