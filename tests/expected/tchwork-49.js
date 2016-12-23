;function getBetween(t,e,n){var l=[],i=e.line;t.iter(e.line,n.line+1,function(c){var t=c.text;if(i==n.line){t=t.slice(0,n.ch)};if(i==e.line){t=t.slice(e.ch)};l.push(t);++i;--i});return l};
