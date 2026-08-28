# -*- coding: utf-8 -*-
import json, os, sys, struct, io
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
sys.path.insert(0, HERE)
os.chdir(ROOT)
from cs import CS, CS_PLURAL, JS, CONTEXT, JS_DIVI, JS_FIELDS, JS_DIVI_FIELDS

strings = json.load(open(os.path.join(HERE, 'strings.json')))
DIVI_FIELDS_HASH = '37263fcddc76f7569ab15d96878f73a5'
PLURAL_FORMS = "nplurals=3; plural=(n==1) ? 0 : ((n>=2 && n<=4) ? 1 : 2);"

def po_escape(text):
    return text.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')

def header(locale, translated):
    lines = [
        'msgid ""', 'msgstr ""',
        '"Project-Id-Version: Course & Schedule Connector for iSport 0.1.0\\n"',
        '"Report-Msgid-Bugs-To: https://github.com/lukasista/course-schedule-connector/issues\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"X-Domain: course-schedule-connector\\n"',
    ]
    if locale:
        lines.insert(3, '"Language: %s\\n"' % locale)
        lines.append('"Plural-Forms: %s\\n"' % PLURAL_FORMS)
    else:
        lines.append('"Plural-Forms: nplurals=2; plural=(n != 1);\\n"')
    return '\n'.join(lines) + '\n'

def extracted():
    """The strings the extractor already found in the PHP.

    Everything listed by hand below is a string no extractor can see — a title
    in a block.json, a word inside a JavaScript file — but a few of them are
    written in PHP as well, and a catalogue that defines one string twice is one
    gettext refuses to read. A string with a context is the same string as
    another only when the context matches too, so the pair is what is kept."""
    return set((entry.get('context'), entry['singular']) for entry in strings)


def extra_entries(translate):
    entries = []
    seen = extracted()
    for (ctxt, msgid), value in CONTEXT.items():
        if (ctxt, msgid) in seen:
            continue
        entries.append((ctxt, msgid, value if translate else '', ['blocks/display/block.json']))
    for msgid, value in JS.items():
        if (None, msgid) in seen:
            continue
        entries.append((None, msgid, value if translate else '', ['blocks/display/editor.js']))
    for msgid, value in JS_DIVI.items():
        if (None, msgid) in seen:
            continue
        if msgid in JS or msgid in CS:
            continue
        entries.append((None, msgid, value if translate else '', ['visual-builder/cscs-divi-display.js']))
    for msgid, value in JS_FIELDS.items():
        if (None, msgid) in seen:
            continue
        if msgid in JS or msgid in JS_DIVI or msgid in CS:
            continue
        entries.append((None, msgid, value if translate else '', ['blocks/fields/editor.js']))
    for msgid, value in JS_DIVI_FIELDS.items():
        if (None, msgid) in seen:
            continue
        if msgid in JS or msgid in JS_DIVI or msgid in JS_FIELDS or msgid in CS:
            continue
        entries.append((None, msgid, value if translate else '', ['visual-builder/cscs-divi-fields.js']))
    return entries

def translation(entry):
    """The Czech for one extracted string, looked up the way it was written.

    A string with a context is two strings that happen to read alike in
    English, so it is looked up by the pair — which is also how gettext will
    look it up at runtime."""
    singular = entry['singular']
    context = entry.get('context')
    if context:
        return CONTEXT[(context, singular)]
    return CS[singular]

def write_po(path, locale, translate):
    out = [header(locale, translate)]
    for entry in strings:
        singular = entry['singular']
        plural = entry['plural']
        context = entry.get('context')
        out.append('\n#: ' + '\n#: '.join(entry['refs']))
        if context:
            out.append('msgctxt "%s"' % po_escape(context))
        out.append('msgid "%s"' % po_escape(singular))
        if plural:
            out.append('msgid_plural "%s"' % po_escape(plural))
            forms = CS_PLURAL[singular] if translate else ['', '']
            for index, form in enumerate(forms):
                out.append('msgstr[%d] "%s"' % (index, po_escape(form)))
        else:
            out.append('msgstr "%s"' % po_escape(translation(entry) if translate else ''))
    for ctxt, msgid, value, refs in extra_entries(translate):
        out.append('\n#: ' + '\n#: '.join(refs))
        if ctxt is not None:
            out.append('msgctxt "%s"' % po_escape(ctxt))
        out.append('msgid "%s"' % po_escape(msgid))
        out.append('msgstr "%s"' % po_escape(value))

    io.open(path, 'w', encoding='utf-8').write('\n'.join(out) + '\n')

def write_mo(path):
    entries = []
    entries.append((b'', header('cs_CZ', True).split('msgstr ""\n', 1)[1].replace('"', '').replace('\\n', '\n').encode('utf-8')))
    for entry in strings:
        singular = entry['singular']
        plural = entry['plural']
        context = entry.get('context')
        if plural:
            key = singular + '\x00' + plural
            value = '\x00'.join(CS_PLURAL[singular])
        else:
            key = singular
            value = translation(entry)
        if context:
            key = context + '\x04' + key
        entries.append((key.encode('utf-8'), value.encode('utf-8')))

    for ctxt, msgid, value, refs in extra_entries(True):
        key = msgid if ctxt is None else (ctxt + '\x04' + msgid)
        entries.append((key.encode('utf-8'), value.encode('utf-8')))

    entries.sort(key=lambda pair: pair[0])
    count = len(entries)
    keystart = 7 * 4 + 16 * count
    offsets = []
    ids = b''
    values = b''
    for key, value in entries:
        offsets.append((len(ids), len(key), len(values), len(value)))
        ids += key + b'\x00'
        values += value + b'\x00'
    valuestart = keystart + len(ids)
    koffsets = []
    voffsets = []
    for o1, l1, o2, l2 in offsets:
        koffsets += [l1, o1 + keystart]
        voffsets += [l2, o2 + valuestart]
    output = struct.pack('Iiiiiii', 0x950412de, 0, count, 7 * 4, 7 * 4 + count * 8, 0, 0)
    output += struct.pack('i' * len(koffsets), *koffsets)
    output += struct.pack('i' * len(voffsets), *voffsets)
    output += ids + values
    open(path, 'wb').write(output)

def write_jed(path, source=None):
    catalogue = {'': {'domain': 'messages', 'lang': 'cs_CZ', 'plural-forms': PLURAL_FORMS}}

    for msgid, value in (source if source is not None else JS).items():
        catalogue[msgid] = [value]

    payload = {
        'translation-revision-date': '2026-08-22 00:00+0000',
        'generator': 'course-schedule-connector',
        'domain': 'messages',
        'locale_data': {'messages': catalogue},
    }

    io.open(path, 'w', encoding='utf-8').write(json.dumps(payload, ensure_ascii=False))

write_jed('languages/course-schedule-connector-cs_CZ-84a9a5368804f03abd39989b3bb2f03e.json')
write_jed('languages/course-schedule-connector-cs_CZ-778c604286e0aee407aeab4fb7e26475.json', JS_DIVI)
write_jed('languages/course-schedule-connector-cs_CZ-93b7686115c9c4edfe5f0d2a6935cf5d.json', JS_FIELDS)
write_jed('languages/course-schedule-connector-cs_CZ-%s.json' % DIVI_FIELDS_HASH, JS_DIVI_FIELDS)
write_po('languages/course-schedule-connector.pot', None, False)
write_po('languages/course-schedule-connector-cs_CZ.po', 'cs_CZ', True)
write_mo('languages/course-schedule-connector-cs_CZ.mo')
print('hotovo')
