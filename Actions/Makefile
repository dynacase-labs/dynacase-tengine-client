pages_html = $(patsubst %.html,$(pubdir)/$(style)/$(appname)/Layout/%.html,$(filter-out $(pages_not_html),$(wildcard *.html)))

include $(utildir)/PubRule

.PHONY: publish

publish: $(pages_html)

$(pubdir)/$(style)/$(appname)/Layout/%.html : %.html $(pubdir)/$(style)/$(appname)/Layout
	$(installcp) $< $@
