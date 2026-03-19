#!/usr/bin/env python3
"""
Generates a package server JSON manifest from a WoltLab package.xml file.
Usage: python3 generate_manifest.py [package.xml path]
Output: {identifier}.json in current directory
"""
import xml.etree.ElementTree as ET
import json
import sys
import os

def generate(xml_path='package.xml'):
    tree = ET.parse(xml_path)
    root = tree.getroot()
    ns = {'w': 'http://www.woltlab.com'}

    identifier = root.attrib.get('name', '')
    version = root.find('.//w:version', ns).text
    date_el = root.find('.//w:date', ns)
    pkg_date = date_el.text if date_el is not None else '2026-01-01'

    names = root.findall('.//w:packagename', ns)
    pkg_name = next((n.text for n in names if not n.attrib.get('language')), names[0].text if names else identifier)

    descs = root.findall('.//w:packagedescription', ns)
    pkg_desc = next((d.text for d in descs if not d.attrib.get('language') or d.attrib.get('language') == 'en'), descs[0].text if descs else '')

    author_el = root.find('.//w:author', ns)
    author = author_el.text if author_el is not None else ''
    authorurl_el = root.find('.//w:authorurl', ns)
    authorurl = authorurl_el.text if authorurl_el is not None else ''

    reqs = [{'package': r.text, 'minversion': r.attrib.get('minversion', '')}
            for r in root.findall('.//w:requiredpackage', ns)]

    fromversions = sorted(set(
        i.attrib.get('fromversion', '')
        for i in root.findall('.//w:instructions', ns)
        if i.attrib.get('fromversion')
    ))

    result = {
        'packages': [{
            'identifier': identifier,
            'packageName': pkg_name,
            'packageDescription': pkg_desc or '',
            'author': author,
            'authorURL': authorurl,
            'isApplication': False,
            'isDisabled': False,
            'versions': [{
                'version': version,
                'packageDate': pkg_date,
                'downloadURL': f'https://amplai.de/pakete/{identifier}_{version}.tar.gz',
                'licenseName': 'Commercial',
                'licenseURL': 'https://amplai.de/lizenz',
                'requireAuth': True,
                'isEnabled': True,
                'requirements': reqs,
                'fromversions': fromversions,
            }]
        }]
    }

    output_file = f'{identifier}.json'
    with open(output_file, 'w') as f:
        json.dump(result, f, indent=2, ensure_ascii=False)
    print(f'Generated {output_file} (v{version})')

if __name__ == '__main__':
    xml_path = sys.argv[1] if len(sys.argv) > 1 else 'package.xml'
    generate(xml_path)
