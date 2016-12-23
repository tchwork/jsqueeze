function getBetween(doc, start, end) {
    var out = [], n = start.line
    doc.iter(start.line, end.line + 1, function (line) {
        var text = line.text
        if (n == end.line) { text = text.slice(0, end.ch) }
        if (n == start.line) { text = text.slice(start.ch) }
        out.push(text)
        ++n
        --n
    })
    return out
}
